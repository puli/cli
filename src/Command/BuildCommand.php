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

use Psr\Log\LogLevel;
use Puli\RepositoryManager\ManagerFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;
use Webmozart\Console\Input\InputOption;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BuildCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('build')
            ->setDescription('Build the resource repository/discovery')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force building even if the repository/discovery is not empty')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output, array(), array(
            LogLevel::WARNING => 'warn',
        ));

        $environment = ManagerFactory::createProjectEnvironment(getcwd());
        $packageManager = ManagerFactory::createPackageManager($environment);
        $repoManager = ManagerFactory::createRepositoryManager($environment, $packageManager);
        $discoveryManager = ManagerFactory::createDiscoveryManager($environment, $packageManager, $logger);

        if ($input->getOption('force')) {
            $repoManager->clearRepository();
            $discoveryManager->clearDiscovery();
        }

        $repoManager->buildRepository();
        $discoveryManager->buildDiscovery();
    }
}
