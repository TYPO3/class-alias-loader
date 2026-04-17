<?php

declare(strict_types=1);

namespace TYPO3\ClassAliasLoader\Test\Unit;

/*
 * This file is part of the class alias loader package.
 *
 * (c) Helmut Hummel <info@helhum.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Composer\Autoload\ClassLoader as ComposerClassLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\ClassAliasLoader\ClassAliasLoader;

/**
 * Test case for ClassAliasLoader
 */
final class ClassAliasLoaderTest extends TestCase
{
    /**
     * @var ClassAliasLoader
     */
    protected $subject;

    /**
     * @var ComposerClassLoader|MockObject
     */
    protected $composerClassLoaderMock;

    public function setUp(): void
    {
        $this->composerClassLoaderMock = $this->getMockBuilder('Composer\\Autoload\\ClassLoader')->getMock();
        $this->subject = new ClassAliasLoader($this->composerClassLoaderMock);
    }

    public function tearDown(): void
    {
        $this->subject->unregister();
    }

    #[Test]
    public function composerLoadClassIsCalledOnlyOnceWhenCaseSensitiveClassLoadingIsOn(): void
    {
        $this->composerClassLoaderMock->expects($this->once())->method('loadClass')->willReturn(true);
        $this->subject->loadClassWithAlias('TestClass');
    }

    #[Test]
    public function loadsClassIfNoAliasIsFound(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        $this->composerClassLoaderMock->expects($this->once())->method('loadClass')->willReturnCallback(function ($className) {
            eval('class ' . $className . ' {}');

            return true;
        });
        $this->subject->loadClassWithAlias($testClassName);
        self::assertTrue(class_exists($testClassName, false));
    }

    #[Test]
    public function callingLoadClassMultipleTimesInEdgeCasesWillStillWork(): void
    {
        $this->composerClassLoaderMock
            ->expects($this->exactly(2))
            ->method('loadClass')
            ->willReturnOnConsecutiveCalls(false, true);
        self::assertFalse($this->subject->loadClassWithAlias('TestClass'));
        self::assertTrue($this->subject->loadClassWithAlias('TestClass'));
    }

    #[Test]
    public function loadClassWithOriginalClassNameSetsAliases(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        $testAlias1 = 'TestAlias' . md5(uniqid('bla', true));
        $testAlias2 = 'TestAlias' . md5(uniqid('bla', true));

        $this->composerClassLoaderMock->expects($this->once())->method('loadClass')->willReturnCallback(function ($className) {
            eval('class ' . $className . ' {}');

            return true;
        });

        $this->subject->setAliasMap([
            'aliasToClassNameMapping' => [
                strtolower($testAlias1) => $testClassName,
                strtolower($testAlias2) => $testClassName,
            ],
            'classNameToAliasMapping' => [
                $testClassName => [strtolower($testAlias1), strtolower($testAlias2)],
            ],
        ]);

        $this->subject->loadClassWithAlias($testClassName);
        self::assertTrue(class_exists($testAlias1, false));
        self::assertTrue(class_exists($testAlias2, false));
    }

    #[Test]
    public function getClassNameForAliasReturnsClassNameForEachAlias(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        $testAlias1 = 'TestAlias' . md5(uniqid('bla', true));
        $testAlias2 = 'TestAlias' . md5(uniqid('bla', true));

        $this->subject->setAliasMap([
            'aliasToClassNameMapping' => [
                strtolower($testAlias1) => $testClassName,
                strtolower($testAlias2) => $testClassName,
            ],
            'classNameToAliasMapping' => [
                $testClassName => [strtolower($testAlias1), strtolower($testAlias2)],
            ],
        ]);

        self::assertEquals($testClassName, $this->subject->getClassNameForAlias($testAlias1));
        self::assertEquals($testClassName, $this->subject->getClassNameForAlias($testAlias2));
    }

    #[Test]
    public function addAliasMapAddsAliasesCorrectlyToTheMap(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        $testAlias1 = 'TestAlias' . md5(uniqid('bla', true));
        $testAlias2 = 'TestAlias' . md5(uniqid('bla', true));

        $this->subject->setAliasMap([
            'aliasToClassNameMapping' => [
                strtolower($testAlias1) => $testClassName,
            ],
            'classNameToAliasMapping' => [
                $testClassName => [strtolower($testAlias1)],
            ],
        ]);

        $this->subject->addAliasMap([
            'aliasToClassNameMapping' => [
                $testAlias2 => $testClassName,
            ],
        ]);

        self::assertEquals($testClassName, $this->subject->getClassNameForAlias($testAlias1));
        self::assertEquals($testClassName, $this->subject->getClassNameForAlias($testAlias2));
    }

    #[Test]
    public function getClassNameForAliasReturnsClassNameForClassName(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        $testAlias1 = 'TestAlias' . md5(uniqid('bla', true));
        $testAlias2 = 'TestAlias' . md5(uniqid('bla', true));

        $this->subject->setAliasMap([
            'aliasToClassNameMapping' => [
                strtolower($testAlias1) => $testClassName,
                strtolower($testAlias2) => $testClassName,
            ],
            'classNameToAliasMapping' => [
                $testClassName => [strtolower($testAlias1), strtolower($testAlias2)],
            ],
        ]);

        self::assertEquals($testClassName, $this->subject->getClassNameForAlias($testClassName));
    }

    #[Test]
    public function getClassNameForAliasReturnsClassNameForClassNameWithNoAliasMapSet(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        self::assertEquals($testClassName, $this->subject->getClassNameForAlias($testClassName));
    }

    #[Test]
    public function loadClassWithAliasClassNameSetsAliasesAndLoadsOriginalClass(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        $testAlias1 = 'TestAlias' . md5(uniqid('bla', true));
        $testAlias2 = 'TestAlias' . md5(uniqid('bla', true));

        $this->composerClassLoaderMock->expects($this->once())->method('loadClass')->willReturnCallback(function ($className) {
            eval('class ' . $className . ' {}');

            return true;
        });

        $this->subject->setAliasMap([
            'aliasToClassNameMapping' => [
                strtolower($testAlias1) => $testClassName,
                strtolower($testAlias2) => $testClassName,
            ],
            'classNameToAliasMapping' => [
                $testClassName => [strtolower($testAlias1), strtolower($testAlias2)],
            ],
        ]);

        $this->subject->loadClassWithAlias($testAlias1);
        self::assertTrue(class_exists($testClassName, false), 'Class name is not loaded');
        self::assertTrue(class_exists($testAlias1, false), 'First alias is not loaded');
        self::assertTrue(class_exists($testAlias2, false), 'Second alias is not loaded');
    }

    #[Test]
    public function aliasesInstancesHaveOriginalClassName(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        $testAlias1 = 'TestAlias' . md5(uniqid('bla', true));
        $testAlias2 = 'TestAlias' . md5(uniqid('bla', true));

        $this->composerClassLoaderMock->expects($this->once())->method('loadClass')->willReturnCallback(function ($className) {
            eval('class ' . $className . ' {}');

            return true;
        });

        $this->subject->setAliasMap([
            'aliasToClassNameMapping' => [
                strtolower($testAlias1) => $testClassName,
                strtolower($testAlias2) => $testClassName,
            ],
            'classNameToAliasMapping' => [
                $testClassName => [strtolower($testAlias1), strtolower($testAlias2)],
            ],
        ]);

        $this->subject->loadClassWithAlias($testClassName);

        $testObject1 = new $testAlias1();
        $testObject2 = new $testAlias2();

        self::assertSame($testClassName, get_class($testObject1));
        self::assertSame($testClassName, get_class($testObject2));
    }

    #[Test]
    public function classAliasesAreGracefullySetIfClassAlreadyExists(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        $testAlias1 = 'TestAlias' . md5(uniqid('bla', true));
        $testAlias2 = 'TestAlias' . md5(uniqid('bla', true));
        $this->composerClassLoaderMock->expects($this->never())->method('loadClass');

        $this->subject->setAliasMap([
            'aliasToClassNameMapping' => [
                strtolower($testAlias1) => $testClassName,
                strtolower($testAlias2) => $testClassName,
            ],
            'classNameToAliasMapping' => [
                $testClassName => [strtolower($testAlias1), strtolower($testAlias2)],
            ],
        ]);

        eval('class ' . $testClassName . ' {}');

        $this->subject->loadClassWithAlias($testClassName);

        $testObject1 = new $testAlias1();
        $testObject2 = new $testAlias2();

        self::assertSame($testClassName, get_class($testObject1));
        self::assertSame($testClassName, get_class($testObject2));
    }

    #[Test]
    public function interfaceAliasesAreGracefullySetIfInterfaceAlreadyExists(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        $testAlias1 = 'TestAlias' . md5(uniqid('bla', true));
        $testAlias2 = 'TestAlias' . md5(uniqid('bla', true));
        $this->composerClassLoaderMock->expects($this->never())->method('loadClass');

        $this->subject->setAliasMap([
            'aliasToClassNameMapping' => [
                strtolower($testAlias1) => $testClassName,
                strtolower($testAlias2) => $testClassName,
            ],
            'classNameToAliasMapping' => [
                $testClassName => [strtolower($testAlias1), strtolower($testAlias2)],
            ],
        ]);

        eval('interface ' . $testClassName . ' {}');

        $this->subject->loadClassWithAlias($testClassName);

        self::assertTrue(interface_exists($testAlias1, false), 'First alias is not loaded');
        self::assertTrue(interface_exists($testAlias2, false), 'Second alias is not loaded');
    }

    #[Test]
    public function classAliasesAreNotReEstablishedIfTheyAlreadyExist(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        $testAlias1 = 'TestAlias' . md5(uniqid('bla', true));
        $testAlias2 = 'TestAlias' . md5(uniqid('bla', true));
        $this->composerClassLoaderMock->expects($this->never())->method('loadClass');

        $this->subject->setAliasMap([
            'aliasToClassNameMapping' => [
                strtolower($testAlias1) => $testClassName,
                strtolower($testAlias2) => $testClassName,
            ],
            'classNameToAliasMapping' => [
                $testClassName => [strtolower($testAlias1), strtolower($testAlias2)],
            ],
        ]);

        eval('class ' . $testClassName . ' {}');
        class_alias($testClassName, $testAlias1);

        $this->subject->loadClassWithAlias($testClassName);

        self::assertTrue(class_exists($testAlias2, false), 'Second alias is not loaded');
    }

    #[Test]
    public function loadClassWithAliasReturnsNullIfComposerClassLoaderCannotFindClass(): void
    {
        $this->composerClassLoaderMock->expects($this->once())->method('loadClass');
        self::assertNull($this->subject->loadClassWithAlias('TestClass'));
    }

    #[Test]
    public function loadClassWithAliasReturnsNullIfComposerClassLoaderCannotFindClassEvenIfItExistsInMap(): void
    {
        $testClassName = 'TestClass' . md5(uniqid('bla', true));
        $testAlias1 = 'TestAlias' . md5(uniqid('bla', true));
        $testAlias2 = 'TestAlias' . md5(uniqid('bla', true));

        $this->subject->setAliasMap([
            'aliasToClassNameMapping' => [
                strtolower($testAlias1) => $testClassName,
                strtolower($testAlias2) => $testClassName,
            ],
            'classNameToAliasMapping' => [
                $testClassName => [strtolower($testAlias1), strtolower($testAlias2)],
            ],
        ]);

        $this->composerClassLoaderMock->expects($this->once())->method('loadClass');
        self::assertNull($this->subject->loadClassWithAlias($testClassName));
    }
}
