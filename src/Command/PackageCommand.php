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
use Puli\RepositoryManager\Package\Package;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Gitty\Command\Command;

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
            ->setDescription('Display the installed packages')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = ManagerFactory::createProjectEnvironment(getcwd());
        $manager = ManagerFactory::createPackageManager($environment);

        $packages = $manager->getPackages()->toArray();
        $packageNames = array_map(function (Package $p) { return $p->getName(); }, $packages);

        sort($packageNames);

        foreach ($packageNames as $packageName) {
            $output->writeln($packageName);
        }

        return 0;
    }
}
