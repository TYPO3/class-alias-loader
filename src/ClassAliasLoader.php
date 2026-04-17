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

use Composer\Autoload\ClassLoader as ComposerClassLoader;

/**
 * The main class loader that amends the composer class loader.
 * It deals with the alias maps.
 */
final class ClassAliasLoader
{
    private array $aliasMap = [
        'aliasToClassNameMapping' => [],
        'classNameToAliasMapping' => [],
    ];

    public function __construct(private readonly ComposerClassLoader $composerClassLoader) {}

    /**
     * Set the alias map
     */
    public function setAliasMap(array $aliasMap): void
    {
        $this->aliasMap = $aliasMap;
    }

    /**
     * Adds an alias map and merges it with already available map
     */
    public function addAliasMap(array $aliasMap): void
    {
        foreach ($aliasMap['aliasToClassNameMapping'] as $alias => $originalClassName) {
            $lowerCaseAlias = strtolower($alias);
            $this->aliasMap['aliasToClassNameMapping'][$lowerCaseAlias] = $originalClassName;
            $this->aliasMap['classNameToAliasMapping'][$originalClassName][$lowerCaseAlias] = $lowerCaseAlias;
        }
    }

    /**
     * Get final class name of alias
     */
    public function getClassNameForAlias(string $aliasOrClassName): string
    {
        return $this->aliasMap['aliasToClassNameMapping'][strtolower($aliasOrClassName)] ?? $aliasOrClassName;
    }

    /**
     * Registers this instance as autoloader.
     */
    public function register(bool $prepend = false): void
    {
        spl_autoload_unregister([$this->composerClassLoader, 'loadClass']);
        spl_autoload_register([$this, 'loadClassWithAlias'], true, $prepend);
    }

    /**
     * Unregisters this instance as an autoloader.
     */
    public function unregister(): void
    {
        spl_autoload_unregister([$this, 'loadClassWithAlias']);
    }

    /**
     * Main class loading method registered with spl_autoload_register()
     */
    public function loadClassWithAlias(string $className): ?bool
    {
        $originalClassName = $this->getOriginalClassName($className);

        return $originalClassName === null
            ? $this->composerClassLoader->loadClass($className)
            : $this->loadOriginalClassAndSetAliases($originalClassName);
    }

    /**
     * Looks up the original class name from the alias map
     * returns null if no alias mapping is found or the original class name as string
     */
    private function getOriginalClassName(string $aliasOrClassName): ?string
    {
        // Is an original class which has an alias
        if (array_key_exists($aliasOrClassName, $this->aliasMap['classNameToAliasMapping'])) {
            return $aliasOrClassName;
        }

        // Is an alias (we're graceful ignoring casing for alias definitions)
        return $this->aliasMap['aliasToClassNameMapping'][strtolower($aliasOrClassName)] ?? null;
    }

    /**
     * Load classes and set aliases.
     * The class_exists calls are safety guards to avoid fatals when
     * class files were included or aliases were set manually in userland code.
     */
    private function loadOriginalClassAndSetAliases(string $originalClassName): ?bool
    {
        if ($this->classExists($originalClassName) || $this->composerClassLoader->loadClass($originalClassName)) {
            foreach ($this->aliasMap['classNameToAliasMapping'][$originalClassName] as $aliasClassName) {
                if (!$this->classExists($aliasClassName)) {
                    class_alias($originalClassName, $aliasClassName);
                }
            }

            return true;
        }

        return null;
    }

    private function classExists(string $className): bool
    {
        return class_exists($className, false)
            || interface_exists($className, false)
            || trait_exists($className, false);
    }
}
