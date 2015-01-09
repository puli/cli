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

use Puli\RepositoryManager\ManagerFactory;
use Puli\RepositoryManager\Package\PackageManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\CompositeCommand;
use Webmozart\Console\Input\InputOption;
use Webmozart\PathUtil\Path;

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
        $factory = new ManagerFactory();
        $environment = $factory->createProjectEnvironment(getcwd());
        $packageManager = $factory->createPackageManager($environment);

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
