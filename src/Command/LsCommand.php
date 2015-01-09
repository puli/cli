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

use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\Iterator\ResourceCollectionIterator;
use Puli\RepositoryManager\ManagerFactory;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;
use Webmozart\Console\Helper\WrappedGrid;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('ls')
            ->setDescription('List the contents of a directory in the resource repository')
            ->addArgument('directory', InputArgument::OPTIONAL, 'The repository path of a directory', '/')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $factory = new ManagerFactory();
        $environment = $factory->createProjectEnvironment(getcwd());
        $repo = $environment->getRepository();
        $stderr = $output instanceof ConsoleOutput ? $output->getErrorOutput() : $output;

        $directory = $repo->get($input->getArgument('directory'));

        if (!$directory instanceof DirectoryResource) {
            $stderr->writeln('Not a directory.');

            return 1;
        }

        $this->listShort($output, $directory->listChildren());

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param Resource[]      $resources
     */
    protected function listShort(OutputInterface $output, $resources)
    {
        $dimensions = $this->getApplication()->getTerminalDimensions();
        $grid = new WrappedGrid($dimensions[0]);
        $grid->setHorizontalSeparator('  ');

        foreach ($resources as $resource) {
            $name = $resource->getName();

            if ($resource->hasChildren()) {
                $name = '<em>'.$name.'</em>';
            }

            $grid->addCell($name);
        }

        $grid->render($output);
    }
}
