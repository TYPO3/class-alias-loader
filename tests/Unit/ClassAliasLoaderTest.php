<?php
namespace Helhum\ClassAliasLoader\Tests\Unit;

/*
 * This file is part of the class alias loader package.
 *
 * (c) Helmut Hummel <info@helhum.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Composer\Autoload\ClassLoader as ComposerClassLoader;
use Helhum\ClassAliasLoader\ClassAliasLoader;

/**
 * Class ClassAliasLoader
 */
class ClassAliasLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ClassAliasLoader
     */
    protected $subject;

    /**
     * @var ComposerClassLoader|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $composerClassLoaderMock;

    public function setUp()
    {
        $this->composerClassLoaderMock = $this->getMock('Composer\\Autoload\\ClassLoader');
        $this->subject = new ClassAliasLoader($this->composerClassLoaderMock);
    }

    /**
     * @test
     */
    public function composerLoadClassIsCalledOnlyOnceWhenCaseSensitiveClassLoadingIsOn() 
    {
        $this->composerClassLoaderMock->expects($this->once())->method('loadClass');
        $this->subject->loadClassWithAlias('TestClass');
    }

    /**
     * @test
     */
    public function composerLoadClassIsCalledOnlyOnceWhenCaseSensitiveClassLoadingIsOffButClassIsFound() 
    {
        $this->composerClassLoaderMock->expects($this->once())->method('loadClass')->willReturn(true);
        $this->subject->setCaseSensitiveClassLoading(false);
        $this->subject->loadClassWithAlias('TestClass');
    }

    /**
     * @test
     */
    public function composerLoadClassIsCalledTwiceWhenCaseSensitiveClassLoadingIsOffAndClassIsNotFound() 
    {
        $this->composerClassLoaderMock->expects($this->exactly(2))->method('loadClass')->willReturn(false);
        $this->subject->setCaseSensitiveClassLoading(false);
        $this->subject->loadClassWithAlias('TestClass');
    }

}
