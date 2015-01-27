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
use Puli\RepositoryManager\Api\Package\PackageState;
use Puli\RepositoryManager\Puli;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Puli\Cli\Console\Command\CompositeCommand;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageCleanCommand extends CompositeCommand
{
    protected function configure()
    {
        $this
            ->setName('package clean')
            ->setDescription('Remove all packages that cannot be found')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $puli = new Puli(getcwd());
        $packageManager = $puli->getPackageManager();

        return $this->cleanPackages($output, $packageManager);
    }

    private function cleanPackages(OutputInterface $output, PackageManager $packageManager)
    {
        foreach ($packageManager->getPackages(PackageState::NOT_FOUND) as $package) {
            $output->writeln('Removing '.$package->getName());
            $packageManager->removePackage($package->getName());
        }

        return 0;
    }
}
