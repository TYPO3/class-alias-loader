<?php

declare(strict_types=1);

namespace TYPO3\ClassAliasLoader;

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
use Composer\Util\Filesystem;
use TYPO3\CMS\Composer\Plugin\Core\IncludeFile\TokenInterface;

class IncludeFile
{
    public const INCLUDE_FILE = '/typo3/alias-loader-include.php';
    public const INCLUDE_FILE_TEMPLATE = '/res/php/alias-loader-include.tmpl.php';

    /**
     * @var TokenInterface[]
     */
    private $tokens;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Composer
     */
    private $composer;

    /**
     * IncludeFile constructor.
     *
     * @param IOInterface $io
     * @param Composer $composer
     * @param TokenInterface[] $tokens
     * @param Filesystem $filesystem
     */
    public function __construct(IOInterface $io, Composer $composer, array $tokens, ?Filesystem $filesystem = null)
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->tokens = $tokens;
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function register()
    {
        $this->io->writeError('<info>Register typo3/class-alias-loader file in root package autoload definition</info>', true, IOInterface::VERBOSE);

        // Generate and write the file
        $config = $this->composer->getConfig();
        $includeFile = $config->get('vendor-dir') . self::INCLUDE_FILE;
        $relativeIncludeFile = $config->get('vendor-dir', ComposerConfig::RELATIVE_PATHS) . self::INCLUDE_FILE;
        file_put_contents($includeFile, $this->getIncludeFileContent(dirname($includeFile)));

        // Register the file in the root package
        $rootPackage = $this->composer->getPackage();
        $autoloadDefinition = $rootPackage->getAutoload();
        $autoloadDefinition['files'][] = $relativeIncludeFile;
        $rootPackage->setAutoload($autoloadDefinition);
    }

    /**
     * Constructs the include file content
     *
     * @param string $includeFilePath
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return string
     */
    protected function getIncludeFileContent($includeFilePath)
    {
        $includeFileTemplate = $this->filesystem->normalizePath(dirname(__DIR__) . self::INCLUDE_FILE_TEMPLATE);
        $includeFileContent = file_get_contents($includeFileTemplate);
        foreach ($this->tokens as $token) {
            $includeFileContent = self::replaceToken($token->getName(), $token->getContent($includeFilePath), $includeFileContent);
        }

        return $includeFileContent;
    }

    /**
     * Replaces a token in the subject (PHP code)
     *
     * @param string $name
     * @param string $content
     * @param string $subject
     * @return string
     */
    private static function replaceToken($name, $content, $subject)
    {
        return str_replace('{$' . $name . '}', $content, $subject);
    }
}
