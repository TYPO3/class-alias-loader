Class Alias Loader [![Build Status](https://travis-ci.org/helhum/class-alias-loader.svg?branch=master)](https://travis-ci.org/helhum/class-alias-loader)
==================


## Introduction
The idea behind this composer package is, to provide backwards compatibility for libraries that want to rename classes
but still want to stay compatible for a certain amount of time with consumer packages of these libraries.

## What it does?
It provides an additional class loader which amends the composer class loader by rewriting the vendor/autoload.php
file when composer dumps the autoload information. This is only done if any of the packages that are installed by composer
provide a class alias map file, which is configured in the respective composer.json.

## How does it work?
If a package provides a mapping file which holds the mapping from old to new class name, the class loader registers itself
and transparently calls `class_alias()` for classes with an alias. If an old class name is requested, the original class
is loaded and the alias is established, so that third party packages can use old class names transparently.

