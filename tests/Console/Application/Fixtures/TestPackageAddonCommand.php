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

use Puli\Cli\Console\Command\CompositeCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestPackageAddonCommand extends CompositeCommand
{
    protected function configure()
    {
        $this
            ->setName('package addon')
            ->addArgument('arg', InputArgument::OPTIONAL)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $arg = $input->getArgument('arg');

        $output->write('"package addon'.($arg ? ' '.$arg : '').'" executed');
    }
}
