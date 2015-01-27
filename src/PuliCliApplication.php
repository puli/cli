<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli;

use Puli\Cli\Command\BindCommand;
use Puli\Cli\Command\BuildCommand;
use Puli\Cli\Command\ConfigCommand;
use Puli\Cli\Command\FindCommand;
use Puli\Cli\Command\LsCommand;
use Puli\Cli\Command\MapCommand;
use Puli\Cli\Command\PackageCleanCommand;
use Puli\Cli\Command\PackageCommand;
use Puli\Cli\Command\PackageInstallCommand;
use Puli\Cli\Command\PackageRemoveCommand;
use Puli\Cli\Command\TreeCommand;
use Puli\Cli\Command\TypeCommand;
use Puli\Cli\Command\TypeDefineCommand;
use Puli\Cli\Command\TypeRemoveCommand;
use Puli\Cli\Console\Application;
use Puli\Cli\Console\Command\HelpCommand;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliCliApplication extends Application
{
    const VERSION = '@package_version@';

    const RELEASE_DATE = '@release_date@';

    public function __construct()
    {
        parent::__construct('puli', self::VERSION);

        $this->setCatchExceptions(true);
        $this->setDefaultCommand('help');
    }

    protected function getDefaultCommands()
    {
        $rootDir = realpath(__DIR__.'/..');

        return array_merge(array(
            new HelpCommand(array(
                'manDir' => $rootDir.'/docs/man',
                'asciiDocDir' => $rootDir.'/docs',
                'commandPrefix' => 'puli-',
                'defaultPage' => 'puli',
            )),
            new BuildCommand(),
            new LsCommand(),
            new PackageCommand(),
            new PackageCleanCommand(),
            new PackageInstallCommand(),
            new PackageRemoveCommand(),
            new TypeCommand(),
            new TypeDefineCommand(),
            new TypeRemoveCommand(),
            new MapCommand(),
            new BindCommand(),
            new ConfigCommand(),
            new FindCommand(),
            new TreeCommand(),
        ));
    }
}
