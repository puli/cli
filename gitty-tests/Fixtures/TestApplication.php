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
use Webmozart\Gitty\GittyApplication;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestApplication extends GittyApplication
{
    public function __construct()
    {
        parent::__construct();

        $this->setDefaultCommand('package');
    }

    protected function getDefaultCommands()
    {
        return array(
            new TestPackCommand(),
            new TestPackageCommand(),
            new TestPackageAddCommand(),
            new TestPackageAddonCommand(),
        );
    }

    protected function getDefaultInputDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),
        ));
    }
}
