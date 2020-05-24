<?php
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
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\ClassAliasLoader\Config;
use TYPO3\ClassAliasLoader\IncludeFile;

/**
 * Test case for IncludeFile
 */
final class IncludeFileTest extends BaseTestCase
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

    public function setUp()
    {
        $this->ioMock = $this->getMockBuilder('Composer\\IO\\IOInterface')->getMock();
        $this->packageMock = $this->getMockBuilder('Composer\\Package\\RootPackageInterface')->getMock();
        $this->composerMock = $this->getMockBuilder('Composer\\Composer')->getMock();
        $configMock = $this->getMockBuilder('Composer\\Config')->getMock();
        $testDir = $this->testDir;
        $configMock->expects(self::any())
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
        $this->composerMock->expects(self::any())
            ->method('getPackage')
            ->willReturn($this->packageMock);
        $this->composerMock->expects(self::any())
            ->method('getConfig')
            ->willReturn($configMock);

        $this->subject = new IncludeFile(
            $this->ioMock,
            $this->composerMock,
            array(
                new IncludeFile\PrependToken($this->ioMock, $configMock),
                new IncludeFile\CaseSensitiveToken($this->ioMock, new Config($this->packageMock, $this->ioMock))
            )
        );
    }

    protected function tearDown()
    {
        unlink($this->testDir . IncludeFile::INCLUDE_FILE);
        rmdir(dirname($this->testDir . IncludeFile::INCLUDE_FILE));
    }


    public function testIncludeFileCanPeWritten()
    {
        $this->subject->register();
        self::assertTrue(file_exists($this->testDir . IncludeFile::INCLUDE_FILE));
    }
}
