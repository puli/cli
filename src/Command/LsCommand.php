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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;
use Webmozart\Console\Helper\WrappedGrid;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LsCommand extends Command
{
    private $currentPath = '/';

    protected function configure()
    {
        $this
            ->setName('ls')
            ->setDescription('List the children of a resource in the repository')
            ->addArgument('path', InputArgument::OPTIONAL, 'The path of a resource', '/')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $factory = new ManagerFactory();
        $environment = $factory->createProjectEnvironment(getcwd());
        $repo = $environment->getRepository();
        $stderr = $output instanceof ConsoleOutput ? $output->getErrorOutput() : $output;
        $path = Path::makeAbsolute($input->getArgument('path'), $this->currentPath);

        $resource = $repo->get($path);

        if (!$resource->hasChildren()) {
            $stderr->writeln(sprintf(
                'fatal: The resource "%s" does not have children.',
                $resource->getPath()
            ));

            return 1;
        }

        $this->listShort($output, $resource->listChildren());

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
