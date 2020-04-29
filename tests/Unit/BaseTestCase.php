<?php

namespace TYPO3\ClassAliasLoader\Test\Unit;

use PHPUnit\Framework\TestCase;

if (class_exists('PHPUnit_Framework_TestCase')) {
class BaseTestCase extends \PHPUnit_Framework_TestCase
{

}
} else {
class BaseTestCase extends TestCase
{

}
}
