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

use Puli\Cli\Style\PuliTableStyle;
use Puli\Cli\Util\StringUtil;
use Puli\Manager\Api\Package\RootPackageFileManager;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;

/**
 * Handles the "config" command.
 *
 * @since  1.0
 *
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
        $raw = !$args->isOptionSet('parsed');
        $userValues = $this->manager->getConfigKeys(false, false, $raw);
        $effectiveValues = $this->manager->getConfigKeys(true, true, $raw);

        $table = new Table(PuliTableStyle::borderless());
        $table->setHeaderRow(array('Config Key', 'User Value', 'Effective Value'));

        foreach ($effectiveValues as $key => $value) {
            $table->addRow(array(
                sprintf('<c1>%s</c1>', $key),
                array_key_exists($key, $userValues)
                    ? StringUtil::formatValue($userValues[$key], false)
                    : '',
                StringUtil::formatValue($value, false),
            ));
        }

        $table->render($io);

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
        $raw = !$args->isOptionSet('parsed');
        $value = $this->manager->getConfigKey($args->getArgument('key'), null, true, $raw);

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
     * Handles the "config -r <key>" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleReset(Args $args)
    {
        $this->manager->removeConfigKey($args->getArgument('key'));

        return 0;
    }
}
