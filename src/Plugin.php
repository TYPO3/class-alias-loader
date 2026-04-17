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
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

/**
 * Class Plugin
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var ClassAliasMapGenerator
     */
    private $aliasMapGenerator;

    /**
     * Apply plugin modifications to composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->aliasMapGenerator = new ClassAliasMapGenerator(
            $composer,
            $io
        );
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        // Nothing to do
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        // Nothing to do
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'pre-autoload-dump' => ['onPreAutoloadDump'],
        ];
    }

    /**
     * @param Event $event
     * @throws \Exception
     * @return bool
     */
    public function onPreAutoloadDump(Event $event): bool
    {
        return $this->aliasMapGenerator->generateAliasMapFiles();
    }
}
