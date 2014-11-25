<?php

/*
 * This file is part of the Puli CLI package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Console;

use Puli\Cli\Console\Command\ListCommand;
use Puli\Cli\Console\Command\UpdateCommand;
use Puli\Cli\PuliCli;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\HelpCommand;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliCliApplication extends Application
{
    public function __construct()
    {
        parent::__construct('Puli', PuliCli::VERSION);

        $this->setCatchExceptions(true);
        $this->setDefaultCommand('help');
    }

    protected function getDefaultCommands()
    {
        return array_merge(parent::getDefaultCommands(), array(
            new HelpCommand(),
            new UpdateCommand(),
            new ListCommand(),
        ));
    }
}
