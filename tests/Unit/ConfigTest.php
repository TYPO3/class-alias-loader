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

use Composer\Package\PackageInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\ClassAliasLoader\Config;

/**
 * Test case for Config
 */
final class ConfigTest extends TestCase
{
    /**
     * @var Config
     */
    protected $subject;

    /**
     * @var PackageInterface|MockObject
     */
    protected $packageMock;

    public function setUp(): void
    {
        $this->packageMock = $this->getMockBuilder(PackageInterface::class)->getMock();
        $this->subject = new Config($this->packageMock);
    }

    #[Test]
    public function throwsExceptionForEmptyKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1444039407);
        $this->subject->get(null);
    }

    #[Test]
    public function defaultConfigIsAppliedWhenNothingIsConfiguredInPackage(): void
    {
        self::assertFalse($this->subject->get('always-add-alias-loader'));
        self::assertNull($this->subject->get('class-alias-maps'));
    }

    #[Test]
    public function aliasMapConfigIsExtracted(): void
    {
        $this->packageMock->expects($this->any())->method('getExtra')->willReturn(
            [
                'typo3/class-alias-loader' => [
                    'class-alias-maps' => [
                        'path/map.php',
                    ],
                ],
            ]
        );

        $subject = new Config($this->packageMock);

        self::assertSame(['path/map.php'], $subject->get('class-alias-maps'));
    }

    #[Test]
    public function otherConfigIsExtracted(): void
    {
        $this->packageMock->expects($this->any())->method('getExtra')->willReturn(
            [
                'typo3/class-alias-loader' => [
                    'always-add-alias-loader' => true,
                ],
            ]
        );

        $subject = new Config($this->packageMock);

        self::assertTrue($subject->get('always-add-alias-loader'));
    }
}
