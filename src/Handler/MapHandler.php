<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Handler;

use Puli\RepositoryManager\Api\Package\PackageManager;
use Puli\RepositoryManager\Api\Repository\RepositoryManager;
use Puli\RepositoryManager\Api\Repository\ResourceMapping;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\Rendering\Canvas;
use Webmozart\Console\Rendering\Element\Table;
use Webmozart\Console\Rendering\Element\TableStyle;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MapHandler
{
    const MODE_REPLACE = 1;

    const MODE_ADD = 2;

    const MODE_REMOVE = 3;

    /**
     * @var RepositoryManager
     */
    private $repoManager;

    /**
     * @var PackageManager
     */
    private $packageManager;

    private $currentPath = '/';

    public function __construct(RepositoryManager $repoManager, PackageManager $packageManager)
    {
        $this->repoManager = $repoManager;
        $this->packageManager = $packageManager;
    }

    public function handleList(Args $args, IO $io)
    {
        $packageNames = $this->getPackageNames($args);

        if (1 === count($packageNames)) {
            $mappings = $this->repoManager->getResourceMappings(reset($packageNames));
            $this->printMappingTable($io, $mappings);

            return 0;
        }

        foreach ($packageNames as $packageName) {
            $mappings = $this->repoManager->getResourceMappings($packageName);

            if (!$mappings) {
                continue;
            }

            $io->writeLine("<b>$packageName</b>");
            $this->printMappingTable($io, $mappings);
            $io->writeLine('');
        }

        return 0;
    }

    public function handleSave(Args $args)
    {
        $repositoryPath = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);
        $pathReferences = $args->getArgument('file');

        if ($this->repoManager->hasResourceMapping($repositoryPath)) {
            $pathReferences = $this->mergePathReferences(
                $this->repoManager->getResourceMapping($repositoryPath),
                $pathReferences
            );
        }

        if (count($pathReferences) > 0) {
            $this->repoManager->addResourceMapping(new ResourceMapping($repositoryPath, $pathReferences));
        } else {
            $this->repoManager->removeResourceMapping($repositoryPath);
        }

        return 0;
    }

    public function handleDelete(Args $args)
    {
        $repositoryPath = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);

        $this->repoManager->removeResourceMapping($repositoryPath);

        return 0;
    }

    private function getPackageNames(Args $args)
    {
        // Display all packages if "all" is set
        if ($args->isOptionSet('all')) {
            return $this->packageManager->getPackages()->getPackageNames();
        }

        $packageNames = array();

        // Display root if "root" option is given or if no option is set
        if ($args->isOptionSet('root') || !$args->isOptionSet('package')) {
            $packageNames[] = $this->packageManager->getRootPackage()->getName();
        }

        foreach ($args->getOption('package') as $packageName) {
            $packageNames[] = $packageName;
        }

        return $packageNames;
    }

    /**
     * @param IO                $io
     * @param ResourceMapping[] $mappings
     */
    private function printMappingTable(IO $io, array $mappings)
    {
        $canvas = new Canvas($io);
        $table = new Table(TableStyle::borderless());

        foreach ($mappings as $mapping) {
            $table->addRow(array(
                '<em>'.$mapping->getRepositoryPath().'</em>',
                implode(', ', $mapping->getPathReferences())
            ));
        }

        $table->render($canvas);
    }

    private function mergePathReferences($pathReferences, $mergeStatements)
    {
        $mode = self::MODE_REPLACE;
        $pathReferences = array_flip($pathReferences);
        $cleared = false;

        foreach ($mergeStatements as $statement) {
            $statement = trim($statement, '/');

            if ('+' === $statement[0]) {
                $pathReference = substr($statement, 1);
                $mode = self::MODE_ADD;
            } elseif ('-' === $statement[0]) {
                $pathReference = substr($statement, 1);
                $mode = self::MODE_REMOVE;
            } else {
                $pathReference = $statement;
            }

            if (!$cleared && self::MODE_REPLACE === $mode) {
                $pathReferences = array();
                $cleared = true;
            }

            if (self::MODE_REMOVE === $mode) {
                unset($pathReferences[$pathReference]);
            } else {
                $pathReferences[$pathReference] = true;
            }
        }

        return array_keys($pathReferences);
    }
}
