<?php
namespace TYPO3\ClassAliasLoader;

/*
 * This file is part of the class alias loader package.
 *
 * (c) Helmut Hummel <info@helhum.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;

/**
 * This class loops over all packages that are installed by composer and
 * looks for configured class alias maps (in composer.json).
 * If at least one is found, the vendor/autoload.php file is rewritten to amend the composer class loader.
 * Otherwise it does nothing.
 */
class ClassAliasMapGenerator
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $IO;

    /**
     * @var bool
     */
    protected $optimizeAutoloadFiles = false;

    /**
     * @param Composer $composer
     * @param IOInterface $IO
     * @param bool $optimizeAutoloadFiles
     */
    public function __construct(Composer $composer, IOInterface $IO = null, $optimizeAutoloadFiles = false)
    {
        $this->composer = $composer;
        $this->IO = $IO ?: new NullIO();
        $this->optimizeAutoloadFiles = $optimizeAutoloadFiles;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function generateAliasMap()
    {
        $config = $this->composer->getConfig();

        $filesystem = new Filesystem();
        $filesystem->ensureDirectoryExists($config->get('vendor-dir'));
        $basePath = $this->extractBasePath($config);
        $vendorPath = $filesystem->normalizePath(realpath($config->get('vendor-dir')));
        $targetDir = $vendorPath . '/composer';
        $filesystem->ensureDirectoryExists($targetDir);

        $mainPackage = $this->composer->getPackage();
        $autoLoadGenerator = $this->composer->getAutoloadGenerator();
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();
        $packageMap = $autoLoadGenerator->buildPackageMap($this->composer->getInstallationManager(), $mainPackage, $localRepo->getCanonicalPackages());

        $aliasToClassNameMapping = array();
        $classNameToAliasMapping = array();
        $classAliasMappingFound = false;

        foreach ($packageMap as $item) {
            /** @var PackageInterface $package */
            list($package, $installPath) = $item;
            $aliasLoaderConfig = $this->getAliasLoaderConfigFromPackage($package);
            if (!empty($aliasLoaderConfig['class-alias-maps'])) {
                if (!is_array($aliasLoaderConfig['class-alias-maps'])) {
                    throw new \Exception('"class-alias-maps" must be an array');
                }
                foreach ($aliasLoaderConfig['class-alias-maps'] as $mapFile) {
                    $mapFilePath = ($installPath ?: $basePath) . '/' . $filesystem->normalizePath($mapFile);
                    if (!is_file($mapFilePath)) {
                        $this->IO->writeError(sprintf('The class alias map file "%s" configured in package "%s" was not found!', $mapFile, $package->getName()));
                    } else {
                        $packageAliasMap = require $mapFilePath;
                        if (!is_array($packageAliasMap)) {
                            throw new \Exception('"Class alias maps" must return an array', 1422625075);
                        }
                        if (!empty($packageAliasMap)) {
                            $classAliasMappingFound = true;
                        }
                        foreach ($packageAliasMap as $aliasClassName => $className) {
                            $lowerCasedAliasClassName = strtolower($aliasClassName);
                            $aliasToClassNameMapping[$lowerCasedAliasClassName] = $className;
                            $classNameToAliasMapping[$className][$lowerCasedAliasClassName] = $lowerCasedAliasClassName;
                        }
                    }
                }
            }
        }

        $mainPackageAliasLoaderConfig = $this->getAliasLoaderConfigFromPackage($mainPackage);
        $alwaysAddAliasLoader = $mainPackageAliasLoaderConfig['always-add-alias-loader'];
        $caseSensitiveClassLoading = $mainPackageAliasLoaderConfig['autoload-case-sensitivity'];

        if (!$alwaysAddAliasLoader && !$classAliasMappingFound && $caseSensitiveClassLoading) {
            // No mapping found in any package and no insensitive class loading active. We return early and skip rewriting
            // Unless user configured alias loader to be always added
            return false;
        }

        $caseSensitiveClassLoadingString = $caseSensitiveClassLoading ? 'true' : 'false';
        $this->IO->write('<info>Generating ' . ($classAliasMappingFound ? ' ' : 'empty ') . 'class alias map file</info>');
        $this->generateAliasMapFile($aliasToClassNameMapping, $classNameToAliasMapping, $targetDir);

        $suffix = null;
        if (!$config->get('autoloader-suffix') && is_readable($vendorPath . '/autoload.php')) {
            $content = file_get_contents($vendorPath . '/autoload.php');
            if (preg_match('{ComposerAutoloaderInit([^:\s]+)::}', $content, $match)) {
                $suffix = $match[1];
            }
        }

        if (!$suffix) {
            $suffix = $config->get('autoloader-suffix') ?: md5(uniqid('', true));
        }

        $prependAutoloader = $config->get('prepend-autoloader') === false ? 'false' : 'true';

        $aliasLoaderInitClassContent = <<<EOF
<?php

// autoload_alias_loader_real.php @generated by typo3/class-alias-loader

class ClassAliasLoaderInit$suffix {

    private static \$loader;

    public static function initializeClassAliasLoader(\$composerClassLoader) {
        if (null !== self::\$loader) {
            return self::\$loader;
        }
        self::\$loader = \$composerClassLoader;

        \$classAliasMap = require __DIR__ . '/autoload_classaliasmap.php';
        \$classAliasLoader = new TYPO3\ClassAliasLoader\ClassAliasLoader(\$composerClassLoader);
        \$classAliasLoader->setAliasMap(\$classAliasMap);
        \$classAliasLoader->setCaseSensitiveClassLoading($caseSensitiveClassLoadingString);
        \$classAliasLoader->register($prependAutoloader);

        TYPO3\ClassAliasLoader\ClassAliasMap::setClassAliasLoader(\$classAliasLoader);

        return self::\$loader;
    }
}

EOF;
        file_put_contents($targetDir . '/autoload_alias_loader_real.php', $aliasLoaderInitClassContent);

        if (!$caseSensitiveClassLoading) {
            $this->IO->write('<info>Re-writing class map to support case insensitive class loading</info>');
            if (!$this->optimizeAutoloadFiles) {
                $this->IO->write('<warning>Case insensitive class loading only works reliably if you use the optimize class loading feature of composer</warning>');
            }
            $this->rewriteClassMapWithLowerCaseClassNames($targetDir);
        }

        $this->IO->write('<info>Inserting class alias loader into main autoload.php file</info>');
        $this->modifyMainAutoloadFile($vendorPath . '/autoload.php', $suffix);

        return true;
    }

    /**
     * @param PackageInterface $package
     * @return array
     * @TODO: refactor into own config object
     */
    protected function getAliasLoaderConfigFromPackage(PackageInterface $package)
    {
        $extraConfig = $this->handleDeprecatedConfigurationInPackage($package);
        $aliasLoaderConfig = array(
                'class-alias-maps' => array(),
                'always-add-alias-loader' => false,
                'autoload-case-sensitivity' => true
        );
        if (isset($extraConfig['typo3/class-alias-loader']['class-alias-maps'])) {
            $aliasLoaderConfig['class-alias-maps'] = (array)$extraConfig['typo3/class-alias-loader']['class-alias-maps'];
        }
        if (isset($extraConfig['typo3/class-alias-loader']['always-add-alias-loader'])) {
            $aliasLoaderConfig['always-add-alias-loader'] = (bool)$extraConfig['typo3/class-alias-loader']['always-add-alias-loader'];
        }
        if (isset($extraConfig['typo3/class-alias-loader']['autoload-case-sensitivity'])) {
            $aliasLoaderConfig['autoload-case-sensitivity'] = (bool)$extraConfig['typo3/class-alias-loader']['autoload-case-sensitivity'];
        }

        return $aliasLoaderConfig;
    }

    /**
     * Ensures backwards compatibility for packages which used helhum/class-alias-loader
     *
     * @param PackageInterface $package
     * @return array
     */
    protected function handleDeprecatedConfigurationInPackage(PackageInterface $package)
    {
        $extraConfig = $package->getExtra();
        $messages = array();
        if (!isset($extraConfig['typo3/class-alias-loader'])) {
            if (isset($extraConfig['helhum/class-alias-loader'])) {
                $extraConfig['typo3/class-alias-loader'] = $extraConfig['helhum/class-alias-loader'];
                $messages[] = sprintf(
                    '<warning>The package "%s" uses "helhum/class-alias-loader" section to define class alias maps, which is deprecated. Please use "typo3/class-alias-loader" instead!</warning>',
                    $package->getName()
                );
            } else {
                $extraConfig['typo3/class-alias-loader'] = array();
                if (isset($extraConfig['class-alias-maps'])) {
                    $extraConfig['typo3/class-alias-loader']['class-alias-maps'] = $extraConfig['class-alias-maps'];
                    $messages[] = sprintf(
                        '<warning>The package "%s" uses "class-alias-maps" section on top level, which is deprecated. Please move this config below the top level key "typo3/class-alias-loader" instead!</warning>',
                        $package->getName()
                    );
                }
                if (isset($extraConfig['autoload-case-sensitivity'])) {
                    $extraConfig['typo3/class-alias-loader']['autoload-case-sensitivity'] = $extraConfig['autoload-case-sensitivity'];
                    $messages[] = sprintf(
                        '<warning>The package "%s" uses "autoload-case-sensitivity" section on top level, which is deprecated. Please move this config below the top level key "typo3/class-alias-loader" instead!</warning>',
                        $package->getName()
                    );
                }
            }
        }
        if (!empty($messages)) {
            $this->IO->writeError($messages);
        }
        return $extraConfig;
    }

    /**
     * @param $autoloadFile
     * @param string $suffix
     */
    protected function modifyMainAutoloadFile($autoloadFile, $suffix)
    {
        $originalAutoloadFileContent = file_get_contents($autoloadFile);
        preg_match('/return ComposerAutoloaderInit[^;]*;/', $originalAutoloadFileContent, $matches);
        $originalAutoloadFileContent = str_replace($matches[0], '', $originalAutoloadFileContent);
        $composerClassLoaderInit = str_replace(array('return ', ';'), '', $matches[0]);
        $autoloadFileContent = <<<EOF
$originalAutoloadFileContent

// autoload.php @generated by typo3/class-alias-loader

require_once __DIR__ . '/composer/autoload_alias_loader_real.php';

return ClassAliasLoaderInit$suffix::initializeClassAliasLoader($composerClassLoaderInit);

EOF;

        file_put_contents($autoloadFile, $autoloadFileContent);

    }

    /**
     * @param array $aliasToClassNameMapping
     * @param array $classNameToAliasMapping
     * @param string $targetDir
     */
    protected function generateAliasMapFile(array $aliasToClassNameMapping, array $classNameToAliasMapping, $targetDir)
    {
        $exportArray = array(
            'aliasToClassNameMapping' => $aliasToClassNameMapping,
            'classNameToAliasMapping' => $classNameToAliasMapping
        );

        $fileContent = '<?php' . chr(10) . 'return ';
        $fileContent .= var_export($exportArray, true);
        $fileContent .= ';';

        file_put_contents($targetDir . '/autoload_classaliasmap.php', $fileContent);
    }

    /**
     * Rewrites the class map to have lowercased keys to be able to load classes with wrong casing
     * Defaults to case sensitivity (composer loader default)
     *
     * @param string $targetDir
     */
    protected function rewriteClassMapWithLowerCaseClassNames($targetDir)
    {
        $classMapContents = file_get_contents($targetDir . '/autoload_classmap.php');
        $classMapContents = preg_replace_callback('/    \'[^\']*\' => /', function ($match) {
            return strtolower($match[0]);
        }, $classMapContents);
        file_put_contents($targetDir . '/autoload_classmap.php', $classMapContents);
    }


    /**
     * Extracts the bas path out of composer config
     *
     * @param \Composer\Config $config
     * @return mixed
     */
    protected function extractBasePath(\Composer\Config $config) {
        $reflectionClass = new \ReflectionClass($config);
        $reflectionProperty = $reflectionClass->getProperty('baseDir');
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->getValue($config);
    }

}
