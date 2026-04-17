<?php

declare(strict_types=1);

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
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use TYPO3\ClassAliasLoader\IncludeFile\PrependToken;
use TYPO3\ClassAliasLoader\IncludeFile\SuffixToken;

/**
 * This class loops over all packages that are installed by composer and
 * looks for configured class alias maps (in composer.json).
 * If at least one is found, the vendor/autoload.php file is rewritten to amend the composer class loader.
 * Otherwise it does nothing.
 */
final readonly class ClassAliasMapGenerator
{
    private Config $config;

    public function __construct(private Composer $composer, private IOInterface $io)
    {
        $this->config = new Config($this->composer->getPackage());
    }

    /**
     * @throws \Exception
     */
    public function generateAliasMapFiles(): bool
    {
        $config = $this->composer->getConfig();

        $filesystem = new Filesystem();
        $basePath = $filesystem->normalizePath(substr($config->get('vendor-dir'), 0, -strlen($config->get('vendor-dir', $config::RELATIVE_PATHS))));
        $vendorPath = $config->get('vendor-dir');
        $targetDir = $vendorPath . '/composer';
        $filesystem->ensureDirectoryExists($targetDir);

        $mainPackage = $this->composer->getPackage();
        $autoLoadGenerator = $this->composer->getAutoloadGenerator();
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();
        $packageMap = $autoLoadGenerator->buildPackageMap($this->composer->getInstallationManager(), $mainPackage, $localRepo->getCanonicalPackages());

        $aliasToClassNameMapping = [];
        $classNameToAliasMapping = [];
        $classAliasMappingFound = false;

        foreach ($packageMap as $item) {
            /** @var PackageInterface $package */
            [$package, $installPath] = $item;
            $aliasLoaderConfig = new Config($package);
            if ($aliasLoaderConfig->get('class-alias-maps') !== null) {
                if (!is_array($aliasLoaderConfig->get('class-alias-maps'))) {
                    throw new \Exception('Configuration option "class-alias-maps" must be an array');
                }
                foreach ($aliasLoaderConfig->get('class-alias-maps') as $mapFile) {
                    $mapFilePath = ($installPath ?: $basePath) . '/' . $filesystem->normalizePath($mapFile);
                    if (!is_file($mapFilePath)) {
                        $this->io->writeError(sprintf('The class alias map file "%s" configured in package "%s" was not found!', $mapFile, $package->getName()));
                        continue;
                    }
                    $packageAliasMap = require $mapFilePath;
                    if (!is_array($packageAliasMap)) {
                        throw new \Exception('Class alias map files must return an array', 1422625075);
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

        $alwaysAddAliasLoader = $this->config->get('always-add-alias-loader');

        if (!$alwaysAddAliasLoader && !$classAliasMappingFound) {
            // No mapping found in any package and no insensitive class loading active. We return early and skip rewriting
            // Unless user configured alias loader to be always added
            return false;
        }

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

        $includeFile = new IncludeFile(
            $this->io,
            $this->composer,
            [
                new SuffixToken(
                    $suffix
                ),
                new PrependToken(
                    $this->composer->getConfig()
                ),
            ]
        );
        $includeFile->register();

        $this->io->write('<info>Generating ' . ($classAliasMappingFound ? '' : 'empty ') . 'class alias map file</info>');
        $this->generateAliasMapFile($aliasToClassNameMapping, $classNameToAliasMapping, $targetDir, $suffix);

        return true;
    }

    private function generateAliasMapFile(array $aliasToClassNameMapping, array $classNameToAliasMapping, string $targetDir, string $suffix): void
    {
        $exportArray = [
            'aliasToClassNameMapping' => $aliasToClassNameMapping,
            'classNameToAliasMapping' => $classNameToAliasMapping,
        ];

        $fileContent = <<<EOF
<?php

// autoload_classaliasmap_static.php @generated by ClassAliasLoader

namespace TYPO3\ClassAliasLoader;

class ClassAliasLoaderStaticInit$suffix
{
    public static \$aliasMap=
EOF;
        $fileContent .= var_export($exportArray, true);
        $fileContent .= ';
}
';
        file_put_contents($targetDir . '/autoload_classaliasmap_static.php', $fileContent);
    }

    /**
     * Rewrites the class map to have lowercased keys to be able to load classes with wrong casing
     * Defaults to case sensitivity (composer loader default)
     *
     * @param string $targetDir
     */
    private function rewriteClassMapWithLowerCaseClassNames(string $targetDir): void
    {
        $classMapContents = file_get_contents($targetDir . '/autoload_classmap.php');
        $classMapContents = preg_replace_callback('/    \'[^\']*\' => /', function ($match) {
            return strtolower($match[0]);
        }, $classMapContents);
        file_put_contents($targetDir . '/autoload_classmap.php', $classMapContents);
    }
}
