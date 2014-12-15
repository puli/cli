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

use Puli\Cli\Command\DumpCommand;
use Puli\Cli\Command\LsCommand;
use Puli\Cli\Command\PackageCommand;
use Webmozart\Gitty\Command\HelpCommand;
use Webmozart\Gitty\GittyApplication;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliCliApplication extends GittyApplication
{
    const VERSION = '@package_version@';

    const RELEASE_DATE = '@release_date@';

    public function __construct()
    {
        parent::__construct('Puli', self::VERSION);

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
            new DumpCommand(),
            new LsCommand(),
            new PackageCommand(),
        ));
    }
}
