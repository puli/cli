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

use Puli\Cli\Util\ArgsUtil;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Repository\RepositoryManager;
use Puli\RepositoryManager\Api\Repository\ResourceMapping;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Console\UI\Style\TableStyle;
use Webmozart\PathUtil\Path;

/**
 * Handles the "map" command.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MapHandler
{
    /**
     * Mode: Replace existing path references.
     *
     * @internal
     */
    const MODE_REPLACE = 1;

    /**
     * Mode: Add path references to the existing path references.
     *
     * @internal
     */
    const MODE_ADD = 2;

    /**
     * Mode: Remove path references from the existing path references.
     *
     * @internal
     */
    const MODE_REMOVE = 3;

    /**
     * @var RepositoryManager
     */
    private $repoManager;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var string
     */
    private $currentPath = '/';

    /**
     * Creates the handler.
     *
     * @param RepositoryManager $repoManager The repository manager.
     * @param PackageCollection $packages    The loaded packages.
     */
    public function __construct(RepositoryManager $repoManager, PackageCollection $packages)
    {
        $this->repoManager = $repoManager;
        $this->packages = $packages;
    }

    /**
     * Handles the "map -l" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handleList(Args $args, IO $io)
    {
        $packageNames = ArgsUtil::getPackageNames($args, $this->packages);

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

    /**
     * Handles the "map" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleSave(Args $args)
    {
        $repositoryPath = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);
        $pathReferences = $args->getArgument('file');

        if ($this->repoManager->hasResourceMapping($repositoryPath)) {
            $pathReferences = $this->applyMergeStatements(
                $this->repoManager->getResourceMapping($repositoryPath)->getPathReferences(),
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

    /**
     * Handles the "map -d" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleDelete(Args $args)
    {
        $repositoryPath = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);

        $this->repoManager->removeResourceMapping($repositoryPath);

        return 0;
    }

    /**
     * Prints resource mappings in a table.
     *
     * @param IO                $io       The I/O.
     * @param ResourceMapping[] $mappings The resource mappings.
     */
    private function printMappingTable(IO $io, array $mappings)
    {
        $table = new Table(TableStyle::borderless());

        foreach ($mappings as $mapping) {
            $table->addRow(array(
                '<em>'.$mapping->getRepositoryPath().'</em>',
                implode(', ', $mapping->getPathReferences())
            ));
        }

        $table->render($io);
    }

    /**
     * Applies merge statements of the form "+path" or "-path" to a set of path
     * references.
     *
     * @param string[] $pathReferences  The path references.
     * @param string[] $mergeStatements The merge statements.
     *
     * @return string[] The resulting path references.
     */
    private function applyMergeStatements(array $pathReferences, array $mergeStatements)
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
