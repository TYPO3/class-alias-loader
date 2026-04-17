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

use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\ClassAliasLoader\Config;

/**
 * Test case for Config
 */
class ConfigTest extends TestCase
{
    /**
     * @var Config
     */
    protected $subject;

    /**
     * @var IOInterface|MockObject
     */
    protected $ioMock;

    /**
     * @var PackageInterface|MockObject
     */
    protected $packageMock;

    public function setUp(): void
    {
        $this->ioMock = $this->getMockBuilder('Composer\\IO\\IOInterface')->getMock();
        $this->packageMock = $this->getMockBuilder('Composer\\Package\\PackageInterface')->getMock();
        $this->subject = new Config($this->packageMock, $this->ioMock);
    }

    /**
     * @test
     */
    public function throwsExceptionForEmptyKey(): void
    {
        // Use this instead when old PHP versions are dropped and minimum phpunit version can be raised:
        /**
        $this->expectException('\\InvalidArgumentException');
        $this->expectExceptionCode(1444039407);
        $this->subject->get(null);
         */
        try {
            $result = false;
            $this->subject->get(null);
        } catch (\InvalidArgumentException $e) {
            if ($e->getCode() === 1444039407) {
                $result = true;
            }
        }
        $this->assertTrue($result, 'Expected exception with expected code not received');
    }

    /**
     * @test
     */
    public function defaultConfigIsAppliedWhenNothingIsConfiguredInPackage(): void
    {
        $this->assertFalse($this->subject->get('always-add-alias-loader'));
        $this->assertTrue($this->subject->get('autoload-case-sensitivity'));
        $this->assertNull($this->subject->get('class-alias-maps'));
    }

    /**
     * @test
     */
    public function aliasMapConfigIsExtracted(): void
    {
        $this->packageMock->expects($this->any())->method('getExtra')->willReturn(
            array(
                'typo3/class-alias-loader' => array(
                    'class-alias-maps' => array(
                        'path/map.php'
                    )
                )
            )
        );

        $subject = new Config($this->packageMock, $this->ioMock);

        $this->assertSame(array('path/map.php'), $subject->get('class-alias-maps'));
    }

    /**
     * @test
     */
    public function aliasMapConfigIsExtractedFromDeprecatedKey(): void
    {
        $this->packageMock->expects($this->any())->method('getExtra')->willReturn(
            array(
                'helhum/class-alias-loader' => array(
                    'class-alias-maps' => array(
                        'path/map.php'
                    )
                )
            )
        );
        $this->ioMock->expects($this->once())->method('writeError');

        $subject = new Config($this->packageMock, $this->ioMock);

        $this->assertSame(array('path/map.php'), $subject->get('class-alias-maps'));
    }

    /**
     * @test
     */
    public function otherConfigIsExtracted(): void
    {
        $this->packageMock->expects($this->any())->method('getExtra')->willReturn(
            array(
                'typo3/class-alias-loader' => array(
                    'always-add-alias-loader' => true,
                    'autoload-case-sensitivity' => false,
                )
            )
        );

        $subject = new Config($this->packageMock, $this->ioMock);

        $this->assertTrue($subject->get('always-add-alias-loader'));
        $this->assertFalse($subject->get('autoload-case-sensitivity'));
    }

    /**
     * @test
     */
    public function otherConfigIsExtractedFromDeprecatedKey(): void
    {
        $this->packageMock->expects($this->any())->method('getExtra')->willReturn(
            array(
                'helhum/class-alias-loader' => array(
                    'always-add-alias-loader' => true,
                    'autoload-case-sensitivity' => false,
                )
            )
        );

        $subject = new Config($this->packageMock, $this->ioMock);

        $this->assertTrue($subject->get('always-add-alias-loader'));
        $this->assertFalse($subject->get('autoload-case-sensitivity'));
    }

    /**
     * @test
     */
    public function caseSensitivityConfigIsExtractedFromVeryDeprecatedKey(): void
    {
        $this->packageMock->expects($this->any())->method('getExtra')->willReturn(
            array(
                'autoload-case-sensitivity' => false,
            )
        );

        $subject = new Config($this->packageMock, $this->ioMock);

        $this->assertFalse($subject->get('autoload-case-sensitivity'));
    }
}
