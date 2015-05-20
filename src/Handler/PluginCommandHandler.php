<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Handler;

use Puli\Manager\Api\Package\RootPackageFileManager;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

/**
 * Handles the "plugin" command.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PluginCommandHandler
{
    /**
     * @var RootPackageFileManager
     */
    private $manager;

    /**
     * Creates the handler.
     *
     * @param RootPackageFileManager $manager The root package file manager.
     */
    public function __construct(RootPackageFileManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Handles the "puli plugin --list" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handleList(Args $args, IO $io)
    {
        $pluginClasses = $this->manager->getPluginClasses();

        if (!$pluginClasses) {
            $io->writeLine('No plugin classes. Use "puli plugin --install <class>" to install a plugin class.');

            return 0;
        }

        foreach ($pluginClasses as $pluginClass) {
            $io->writeLine("<c1>$pluginClass</c1>");
        }

        return 0;
    }

    /**
     * Handles the "puli plugin --install" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleInstall(Args $args)
    {
        $pluginClass = $args->getArgument('class');

        if ($this->manager->hasPluginClass($pluginClass)) {
            throw new RuntimeException(sprintf(
                'The plugin class "%s" is already installed.',
                $pluginClass
            ));
        }

        $this->manager->addPluginClass($pluginClass);

        return 0;
    }

    /**
     * Handles the "puli plugin --remove" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleDelete(Args $args)
    {
        $pluginClass = $args->getArgument('class');

        if (!$this->manager->hasPluginClass($pluginClass)) {
            throw new RuntimeException(sprintf(
                'The plugin class "%s" is not installed.',
                $pluginClass
            ));
        }

        $this->manager->removePluginClass($pluginClass);

        return 0;
    }
}
