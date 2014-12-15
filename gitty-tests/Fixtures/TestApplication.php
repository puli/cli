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

use Symfony\Component\Console\Input\InputOption;
use Webmozart\Gitty\GittyApplication;
use Webmozart\Gitty\Input\InputDefinition;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestApplication extends GittyApplication
{
    private $terminalDimensions;

    public function __construct($terminalDimensions = array(null, null))
    {
        parent::__construct('Test Application', '1.0.0', 'test-bin');

        $this->terminalDimensions = $terminalDimensions;
    }

    public function getTerminalDimensions()
    {
        return $this->terminalDimensions;
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
            new InputOption('help', 'h', InputOption::VALUE_NONE, 'Display help about the command.'),
            new InputOption('quiet', 'q', InputOption::VALUE_NONE, 'Do not output any message.'),
            new InputOption('verbose', '', InputOption::VALUE_NONE, 'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug.'),
            new InputOption('version', 'V', InputOption::VALUE_NONE, 'Display this application version.'),
            new InputOption('ansi', '', InputOption::VALUE_NONE, 'Force ANSI output.'),
            new InputOption('no-ansi', '', InputOption::VALUE_NONE, 'Disable ANSI output.'),
            new InputOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Do not ask any interactive question.'),
        ));
    }
}
