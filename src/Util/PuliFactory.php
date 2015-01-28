<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Util;

use Psr\Log\LogLevel;
use Puli\RepositoryManager\Puli;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates {@link Puli} instances.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliFactory
{
    /**
     * Creates a {@link Puli} instance.
     *
     * @param OutputInterface $output The console output.
     *
     * @return Puli The created instance.
     */
    public static function createPuli(OutputInterface $output)
    {
        $puli = new Puli(getcwd());
        $puli->setLogger(new ConsoleLogger($output, array(), array(
            LogLevel::WARNING => 'warn',
        )));

        return $puli;
    }

    private function __construct()
    {
    }
}
