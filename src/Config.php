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

use Composer\Package\PackageInterface;

/**
 * Class Config
 */
class Config
{
    /**
     * Default values
     */
    protected array $config = [
        'class-alias-maps' => null,
        'always-add-alias-loader' => false,
    ];

    /**
     * @param PackageInterface $package
     */
    public function __construct(PackageInterface $package)
    {
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
        $configParts = str_getcsv($configKey, '.', '"', '\\');

        // Loop through each part and extract its value
        $value = $this->config;
        foreach ($configParts as $segment) {
            if (array_key_exists($segment, $value)) {
                // Replace current value with child
                $value = $value[$segment];
            } else {
                return null;
            }
        }

        return $value;
    }

    protected function setAliasLoaderConfigFromPackage(PackageInterface $package): void
    {
        $extraConfig = $package->getExtra();
        if (isset($extraConfig['typo3/class-alias-loader']['class-alias-maps'])) {
            $this->config['class-alias-maps'] = (array)$extraConfig['typo3/class-alias-loader']['class-alias-maps'];
        }
        if (isset($extraConfig['typo3/class-alias-loader']['always-add-alias-loader'])) {
            $this->config['always-add-alias-loader'] = (bool)$extraConfig['typo3/class-alias-loader']['always-add-alias-loader'];
        }
    }
}
