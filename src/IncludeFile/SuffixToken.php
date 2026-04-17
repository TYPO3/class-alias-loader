<?php

declare(strict_types=1);

namespace TYPO3\ClassAliasLoader\IncludeFile;

/*
 * This file is part of the class alias loader package.
 *
 * (c) Helmut Hummel <info@helhum.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class SuffixToken implements TokenInterface
{
    /**
     * @var string
     */
    private $name = 'suffix';

    public function __construct(public readonly string $suffix) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getContent(string $includeFilePath): string
    {
        return $this->suffix;
    }
}
