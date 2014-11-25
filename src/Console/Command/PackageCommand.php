<?php

/*
 * This file is part of the Puli CLI package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Console\Command;

use Puli\PackageManager\ManagerFactory;
use Puli\PackageManager\Package\Package;
use Puli\Repository\ResourceRepositoryInterface;
use Puli\Resource\DirectoryResourceInterface;
use Puli\Resource\Iterator\ResourceCollectionIterator;
use Puli\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('package')
            ->setDescription('Displays the installed packages.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = ManagerFactory::createProjectEnvironment(getcwd());
        $manager = ManagerFactory::createPackageManager($environment);

        $packageNames = array_map(function (Package $p) { return $p->getName(); }, $manager->getPackages());

        sort($packageNames);

        foreach ($packageNames as $packageName) {
            $output->writeln($packageName);
        }

        return 0;
    }
}
