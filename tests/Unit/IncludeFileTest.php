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

use Composer\Composer;
use Composer\Config as ComposerConfig;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\ClassAliasLoader\IncludeFile;

final class IncludeFileTest extends TestCase
{
    /**
     * @var IncludeFile
     */
    private $subject;

    /**
     * @var IOInterface|MockObject
     */
    private $ioMock;

    /**
     * @var PackageInterface|MockObject
     */
    private $packageMock;

    /**
     * @var Composer|MockObject
     */
    private $composerMock;

    private $testDir = __DIR__;

    public function setUp(): void
    {
        $this->ioMock = $this->getMockBuilder(IOInterface::class)->getMock();
        $this->packageMock = $this->getMockBuilder(RootPackageInterface::class)->getMock();
        $this->composerMock = $this->getMockBuilder(Composer::class)->getMock();
        $configMock = $this->getMockBuilder(ComposerConfig::class)->getMock();
        $testDir = $this->testDir;
        $configMock->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($testDir) {
                switch ($key) {
                    case 'prepend-autoloader':
                        return true;
                    case 'vendor-dir':
                        return $testDir;
                    default:
                        throw new \RuntimeException('Not expected to be called with ' . $key);
                }
            });
        mkdir($testDir . '/typo3');
        $this->composerMock->expects($this->any())
            ->method('getPackage')
            ->willReturn($this->packageMock);
        $this->composerMock->expects($this->any())
            ->method('getConfig')
            ->willReturn($configMock);

        $this->subject = new IncludeFile(
            $this->ioMock,
            $this->composerMock,
            [
                new IncludeFile\PrependToken($configMock),
            ]
        );
    }

    public function tearDown(): void
    {
        unlink($this->testDir . IncludeFile::INCLUDE_FILE);
        rmdir(dirname($this->testDir . IncludeFile::INCLUDE_FILE));
    }

    #[Test]
    public function includeFileCanBeWritten(): void
    {
        $this->subject->register();
        self::assertTrue(file_exists($this->testDir . IncludeFile::INCLUDE_FILE));
    }
}
