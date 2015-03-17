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

use Puli\Cli\Util\StringUtil;
use Puli\RepositoryManager\Api\Package\RootPackageFileManager;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

/**
 * Handles the "config" command.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigCommandHandler
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
     * Handles the "config" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handleList(Args $args, IO $io)
    {
        $includeFallback = $args->isOptionSet('all');
        $includeUnset = $args->isOptionSet('all');
        $values = $this->manager->getConfigKeys($includeFallback, $includeUnset);

        foreach ($values as $key => $value) {
            $io->writeLine("<comment>$key</comment> = ".StringUtil::formatValue($value, false));
        }

        return 0;
    }

    /**
     * Handles the "config <key>" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handleShow(Args $args, IO $io)
    {
        $value = $this->manager->getConfigKey($args->getArgument('key'), null, true);

        $io->writeLine(StringUtil::formatValue($value, false));

        return 0;
    }

    /**
     * Handles the "config <key> <value>" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleSet(Args $args)
    {
        $value = StringUtil::parseValue($args->getArgument('value'));

        $this->manager->setConfigKey($args->getArgument('key'), $value);

        return 0;
    }

    /**
     * Handles the "config -d <key>" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleDelete(Args $args)
    {
        $this->manager->removeConfigKey($args->getArgument('key'));

        return 0;
    }
}
