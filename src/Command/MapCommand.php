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
use Puli\RepositoryManager\Api\Repository\RepositoryManager;
use Puli\RepositoryManager\Api\Repository\ResourceMapping;
use Puli\RepositoryManager\Puli;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;
use Webmozart\Console\Input\InputOption;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MapCommand extends Command
{
    const MODE_REPLACE = 1;

    const MODE_ADD = 2;

    const MODE_REMOVE = 3;

    private $currentPath = '/';

    protected function configure()
    {
        $this
            ->setName('map')
            ->setDescription('Display and change resource mappings')
            ->addArgument('path', InputArgument::OPTIONAL)
            ->addArgument('file', InputArgument::OPTIONAL | InputArgument::IS_ARRAY)
            ->addOption('root', null, InputOption::VALUE_NONE, 'Show mappings of the root package')
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Show mappings of a package', null, 'package')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Show mappings of all packages')
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete a mapping')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $puli = new Puli(getcwd());
        $packageManager = $puli->getPackageManager();
        $repoManager = $puli->getRepositoryManager();

        if ($input->getOption('delete')) {
            return $this->deleteMapping(
                Path::makeAbsolute($input->getArgument('path'), $this->currentPath),
                $repoManager
            );
        }

        if ($input->getArgument('path')) {
            return $this->updateMapping(
                Path::makeAbsolute($input->getArgument('path'), $this->currentPath),
                $input->getArgument('file'),
                $repoManager
            );
        }

        $packageNames = $this->getPackageNames($input, $packageManager);

        return $this->listResourceMappings($output, $repoManager, $packageNames);
    }

    /**
     * @param string            $repositoryPath
     * @param string[]          $filesystemPaths
     * @param RepositoryManager $repoManager
     *
     * @return int
     */
    private function updateMapping($repositoryPath, array $filesystemPaths, RepositoryManager $repoManager)
    {
        $filesystemPaths = $this->mergeFilesystemPaths(
            $repoManager->hasResourceMapping($repositoryPath)
                ? $repoManager->getResourceMapping($repositoryPath)->getFilesystemPaths()
                : array(),
            $filesystemPaths
        );

        if (count($filesystemPaths) > 0) {
            $repoManager->addResourceMapping(new ResourceMapping($repositoryPath, $filesystemPaths));
        } else {
            $repoManager->removeResourceMapping($repositoryPath);
        }

        return 0;
    }

    /**
     * @param string            $repositoryPath
     * @param RepositoryManager $repoManager
     *
     * @return int
     */
    private function deleteMapping($repositoryPath, RepositoryManager $repoManager)
    {
        $repoManager->removeResourceMapping($repositoryPath);

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param RepositoryManager $repoManager
     *
     * @return int
     */
    private function listResourceMappings(OutputInterface $output, RepositoryManager $repoManager, $packageNames = null)
    {
        if (1 === count($packageNames)) {
            $mappings = $repoManager->getResourceMappings(reset($packageNames));
            $this->printMappingTable($output, $mappings);

            return 0;
        }

        foreach ($packageNames as $packageName) {
            $mappings = $repoManager->getResourceMappings($packageName);

            if (!$mappings) {
                continue;
            }

            $output->writeln("<b>$packageName</b>");
            $this->printMappingTable($output, $mappings);
            $output->writeln('');
        }

        return 0;
    }

    /**
     * @param InputInterface $input
     * @param PackageManager $packageManager
     *
     * @return string[]|null
     */
    private function getPackageNames(InputInterface $input, PackageManager $packageManager)
    {
        // Display all packages if "all" is set
        if ($input->getOption('all')) {
            return $packageManager->getPackages()->getPackageNames();
        }

        $packageNames = array();

        // Display root if "root" option is given or if no option is set
        if ($input->getOption('root') || !$input->getOption('package')) {
            $packageNames[] = $packageManager->getRootPackage()->getName();
        }

        foreach ($input->getOption('package') as $packageName) {
            $packageNames[] = $packageName;
        }

        return $packageNames;
    }

    /**
     * @param OutputInterface   $output
     * @param ResourceMapping[] $mappings
     */
    private function printMappingTable(OutputInterface $output, array $mappings)
    {
        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('');

        foreach ($mappings as $mapping) {
            $table->addRow(array(
                '<em>'.$mapping->getRepositoryPath().'</em>',
                ' '.implode(', ', $mapping->getFilesystemPaths())
            ));
        }

        $table->render();
    }

    private function mergeFilesystemPaths($filesystemPaths, $mergedPaths)
    {
        $mode = self::MODE_REPLACE;
        $filesystemPaths = array_flip($filesystemPaths);
        $cleared = false;

        foreach ($mergedPaths as $filesystemPath) {
            $filesystemPath = trim($filesystemPath, '/');

            if ('+' === $filesystemPath[0]) {
                $filesystemPath = substr($filesystemPath, 1);
                $mode = self::MODE_ADD;
            } elseif ('-' === $filesystemPath[0]) {
                $filesystemPath = substr($filesystemPath, 1);
                $mode = self::MODE_REMOVE;
            }

            if (!$cleared && self::MODE_REPLACE === $mode) {
                $filesystemPaths = array();
                $cleared = true;
            }

            if (self::MODE_REMOVE === $mode) {
                unset($filesystemPaths[$filesystemPath]);
            } else {
                $filesystemPaths[$filesystemPath] = true;
            }
        }

        return array_keys($filesystemPaths);
    }
}
