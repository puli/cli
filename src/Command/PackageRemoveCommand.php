<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Command;

use Puli\RepositoryManager\Api\Package\PackageManager;
use Puli\RepositoryManager\Puli;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Puli\Cli\Console\Command\CompositeCommand;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageRemoveCommand extends CompositeCommand
{
    protected function configure()
    {
        $this
            ->setName('package remove')
            ->setDescription('Remove a package')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the package')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $puli = new Puli(getcwd());
        $packageManager = $puli->getPackageManager();

        return $this->removePackage(
            $input->getArgument('name'),
            $packageManager
        );
    }

    private function removePackage($packageName, PackageManager $packageManager)
    {
        $packageManager->removePackage($packageName);

        return 0;
    }
}
