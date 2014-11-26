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

use Puli\RepositoryManager\ManagerFactory;
use Puli\Resource\DirectoryResourceInterface;
use Puli\Resource\Iterator\ResourceCollectionIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ListCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('list')
            ->setDescription('Lists the contents of a directory in the resource repository.')
            ->addArgument('directory', InputArgument::OPTIONAL, 'The repository path of a directory.', '/')
            ->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Recursively list the contents of sub-directories.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ConsoleOutputInterface $output */
        $environment = ManagerFactory::createProjectEnvironment(getcwd());
        $repo = ManagerFactory::createRepository($environment);

        $directory = $repo->get($input->getArgument('directory'));

        if (!$directory instanceof DirectoryResourceInterface) {
            $output->getErrorOutput()->writeln('Not a directory.');

            return 1;
        }

        $iterator = $directory->listEntries();

        if ($input->getOption('recursive')) {
            $iterator = new \RecursiveIteratorIterator(
                new ResourceCollectionIterator(
                    $iterator
                ),
                \RecursiveIteratorIterator::SELF_FIRST
            );
        }

        foreach ($iterator as $resource) {
            $output->writeln($resource->getPath());
        }

        return 0;
    }
}
