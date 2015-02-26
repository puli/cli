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
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigHandler
{
    /**
     * @var RootPackageFileManager
     */
    private $manager;

    public function __construct(RootPackageFileManager $manager)
    {
        $this->manager = $manager;
    }

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

    public function handleShow(Args $args, IO $io)
    {
        $value = $this->manager->getConfigKey($args->getArgument('key'), null, true);

        $io->writeLine(StringUtil::formatValue($value, false));

        return 0;
    }

    public function handleSet(Args $args)
    {
        $this->manager->setConfigKey($args->getArgument('key'), $args->getArgument('value'));

        return 0;
    }
}
