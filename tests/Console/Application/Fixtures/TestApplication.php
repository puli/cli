<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Tests\Console\Application\Fixtures;

use Puli\Cli\Console\Application\CompositeCommandApplication;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestApplication extends CompositeCommandApplication
{
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
