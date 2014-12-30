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
use Puli\RepositoryManager\Repository\ResourceMapping;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MapCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('map')
            ->setDescription('Show and manipulate resource mappings.')
            ->addArgument('repository-path', InputArgument::REQUIRED)
            ->addArgument('path', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = ManagerFactory::createProjectEnvironment(getcwd());
        $manager = ManagerFactory::createRepositoryManager($environment);

        $manager->addResourceMapping(new ResourceMapping(
            $input->getArgument('repository-path'),
            $input->getArgument('path')
        ));
    }
}