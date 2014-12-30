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
use Puli\RepositoryManager\Repository\RepositoryManager;
use Puli\RepositoryManager\Repository\ResourceMapping;
use Symfony\Component\Console\Helper\Table;
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
            ->addArgument('repository-path', InputArgument::OPTIONAL)
            ->addArgument('path', InputArgument::OPTIONAL | InputArgument::IS_ARRAY)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = ManagerFactory::createProjectEnvironment(getcwd());
        $manager = ManagerFactory::createRepositoryManager($environment);

        if ($input->getArgument('repository-path')) {
            return $this->addResourceMapping($input, $manager);
        }

        return $this->listResourceMappings($output, $manager);
    }

    /**
     * @param InputInterface $input
     * @param RepositoryManager $manager
     *
     * @return int
     */
    protected function addResourceMapping(InputInterface $input, RepositoryManager $manager)
    {
        $manager->addResourceMapping(new ResourceMapping(
            $input->getArgument('repository-path'),
            $input->getArgument('path')
        ));

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param RepositoryManager $manager
     *
     * @return int
     */
    protected function listResourceMappings(OutputInterface $output, RepositoryManager $manager)
    {
        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('');

        foreach ($manager->getResourceMappings() as $mapping) {
            $table->addRow(array(
                '<em>'.$mapping->getRepositoryPath().'</em>:',
                ' <tt>'.implode('</tt>, <tt>', $mapping->getFilesystemPaths()).'</tt>'
            ));
        }

        $table->render();

        return 0;
    }
}
