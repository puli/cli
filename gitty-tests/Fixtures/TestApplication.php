<?php

/*
 * This file is part of the webmozart/gitty package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\Gitty\Tests\Fixtures;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Webmozart\Gitty\GittyApplication;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestApplication extends GittyApplication
{
    public function __construct()
    {
        parent::__construct('Test Application', '1.0.0');
    }

    protected function getDefaultCommands()
    {
        return array_merge(parent::getDefaultCommands(), array(
            new TestPackCommand(),
            new TestPackageCommand(),
            new TestPackageAddCommand(),
            new TestPackageAddonCommand(),
        ));
    }

    protected function getDefaultInputDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('main-command', InputArgument::REQUIRED, 'The command to launch'),
            new InputOption('help', 'h', InputOption::VALUE_NONE, 'Help')
        ));
    }
}
