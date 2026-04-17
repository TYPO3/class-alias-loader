<?php

declare(strict_types=1);

$composerAutoLoader = require dirname(__DIR__) . '/autoload.php';
$classAliasLoader = new TYPO3\ClassAliasLoader\ClassAliasLoader($composerAutoLoader);
require_once __DIR__ . '/autoload_classaliasmap_static.php';
$classAliasLoader->setAliasMap(TYPO3\ClassAliasLoader\ClassAliasLoaderStaticInit{$suffix}::$aliasMap);
$classAliasLoader->register({$prepend});
TYPO3\ClassAliasLoader\ClassAliasMap::setClassAliasLoader($classAliasLoader);
