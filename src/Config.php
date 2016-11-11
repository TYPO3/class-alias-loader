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

use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\PackageInterface;

/**
 * Class Config
 */
class Config
{
    const OPTION_CLASS_ALIAS_MAPS = 'class-alias-maps';
    const OPTION_ALWAYS_ADD_ALIAS_LOADER = 'always-add-alias-loader';
    const OPTION_AUTOLOAD_IS_CASE_SENSITIVE = 'autoload-case-sensitivity';
    const OPTION_AUTOLOAD_MODE = 'autoload-mode';

    const AUTOLOAD_MODE_NORMAL = 'normal';
    const AUTOLOAD_MODE_FORCE_ALIAS_LOADING = 'force-alias-loading';

    /**
     * Default config values
     *
     * @var array
     */
    protected $config = array(
        self::OPTION_CLASS_ALIAS_MAPS => null,
        self::OPTION_ALWAYS_ADD_ALIAS_LOADER => false,
        self::OPTION_AUTOLOAD_IS_CASE_SENSITIVE => true,
        self::OPTION_AUTOLOAD_MODE => self::AUTOLOAD_MODE_NORMAL,
    );

    /**
     * Config cast types
     *
     * @var array
     */
    protected $configCastType = array(
        self::OPTION_CLASS_ALIAS_MAPS => 'array',
        self::OPTION_ALWAYS_ADD_ALIAS_LOADER => 'bool',
        self::OPTION_AUTOLOAD_IS_CASE_SENSITIVE => 'bool',
        self::OPTION_AUTOLOAD_MODE => 'string',
    );

     /**
     * @var IOInterface
     */
    protected $IO;

    /**
     * @param PackageInterface $package
     * @param IOInterface $IO
     */
    public function __construct(PackageInterface $package, IOInterface $IO = null)
    {
        $this->IO = $IO ?: new NullIO();
        $this->setAliasLoaderConfigFromPackage($package);
    }

    /**
     * @param string $configKey
     * @return mixed
     */
    public function get($configKey)
    {
        if (empty($configKey)) {
            throw new \InvalidArgumentException('Configuration key must not be empty', 1444039407);
        }
        // Extract parts of the path
        $configKey = str_getcsv($configKey, '.');
        // Loop through each part and extract its value
        $value = $this->config;
        foreach ($configKey as $segment) {
            if (array_key_exists($segment, $value)) {
                // Replace current value with child
                $value = $value[$segment];
            } else {
                return null;
            }
        }
        return $value;
    }


    /**
     * @param PackageInterface $package
     */
    protected function setAliasLoaderConfigFromPackage(PackageInterface $package)
    {
        $extraConfig = $this->handleDeprecatedConfigurationInPackage($package);

        foreach ($this->configCastType as $key => $type) {

            if (isset($extraConfig['typo3/class-alias-loader'][$key])) {
                $value = $extraConfig['typo3/class-alias-loader'][$key];

                // Cast correct type
                switch ($type) {
                    case 'bool':
                        $value = (bool) $value;
                        break;
                    case 'array':
                        $value = (array) $value;
                        break;
                    case 'string':
                        $value = (string) $value;
                        break;
                }

                // Save value
                $this->config[$key] = $value;
            }
        }
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
                if (isset($extraConfig[self::OPTION_CLASS_ALIAS_MAPS])) {
                    $extraConfig['typo3/class-alias-loader'][self::OPTION_CLASS_ALIAS_MAPS] = $extraConfig[self::OPTION_CLASS_ALIAS_MAPS];
                    $messages[] = sprintf(
                        '<warning>The package "%s" uses "class-alias-maps" section on top level, which is deprecated. Please move this config below the top level key "typo3/class-alias-loader" instead!</warning>',
                        $package->getName()
                    );
                }
                if (isset($extraConfig[self::OPTION_AUTOLOAD_IS_CASE_SENSITIVE])) {
                    $extraConfig['typo3/class-alias-loader'][self::OPTION_AUTOLOAD_IS_CASE_SENSITIVE] = $extraConfig[self::OPTION_AUTOLOAD_IS_CASE_SENSITIVE];
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

}
