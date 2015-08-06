<?php
namespace Helhum\ClassAliasLoader;

/*
 * This file is part of the class alias loader package.
 *
 * (c) Helmut Hummel <info@helhum.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This class is the only public API of this package (besides the composer.json configuration)
 * Use the only method in cases described below.
 */
class ClassAliasMap
{
    /**
     * @var ClassAliasLoader
     */
    public static $classAliasLoader;

    /**
     * You can use this method in your code if you compare class names as strings and want to provide compatibility for that as well.
     * The impact is pretty low and boils down to a method call. In case no aliases are present in the composer installation,
     * the class name given is returned as is, because the vendor/autoload.php will not be rewritten and thus the static member of this
     * class will not be set. 
     * 
     * @param string $classNameOrAlias
     * @return string
     */
    public static function getClassNameForAlias($classNameOrAlias) {
        if (!static::$classAliasLoader) {
            return $classNameOrAlias;
        }
        return static::$classAliasLoader->getClassNameForAlias($classNameOrAlias);
    }

}