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
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Repository\PathConflict;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Api\Repository\PathMappingState;
use Puli\Manager\Api\Repository\RepositoryManager;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Console\UI\Style\TableStyle;
use Webmozart\Expression\Expr;
use Webmozart\PathUtil\Path;

/**
 * Handles the "puli map" command.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MapCommandHandler
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
     * Handles the "puli map --list" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handleList(Args $args, IO $io)
    {
        $packageNames = ArgsUtil::getPackageNames($args, $this->packages);
        $states = $this->getPathMappingStates($args);

        $printState = count($states) > 1;
        $printPackageName = count($packageNames) > 1;
        $printHeaders = $printState || $printPackageName;
        $printAdvice = true;

        foreach ($states as $state) {
            $statePrinted = !$printState;

            if (PathMappingState::CONFLICT === $state) {
                $expr = Expr::in($packageNames, PathMapping::CONTAINING_PACKAGE)
                    ->andSame($state, PathMapping::STATE);

                $mappings = $this->repoManager->findPathMappings($expr);

                if (!$mappings) {
                    continue;
                }

                $printAdvice = false;

                if ($printState) {
                    $this->printPathMappingStateHeader($io, $state);
                }

                $this->printConflictTable($io, $mappings, $printState);

                if ($printHeaders) {
                    $io->writeLine('');
                }

                continue;
            }

            foreach ($packageNames as $packageName) {
                $expr = Expr::same($packageName, PathMapping::CONTAINING_PACKAGE)
                    ->andSame($state, PathMapping::STATE);

                $mappings = $this->repoManager->findPathMappings($expr);

                if (!$mappings) {
                    continue;
                }

                $printAdvice = false;

                if (!$statePrinted) {
                    $this->printPathMappingStateHeader($io, $state);
                    $statePrinted = true;
                }

                if ($printPackageName) {
                    $prefix = $printState ? '    ' : '';
                    $io->writeLine("<b>$prefix$packageName</b>");
                }

                $this->printMappingTable($io, $mappings, $printState, PathMappingState::ENABLED === $state);

                if ($printHeaders) {
                    $io->writeLine('');
                }
            }
        }

        if ($printAdvice) {
            $io->writeLine('No path mappings. Use "puli map <path> <file>" to map a Puli path to a file or directory.');
        }

        return 0;
    }

    /**
     * Handles the "puli map" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleAdd(Args $args)
    {
        $flags = $args->isOptionSet('force')
            ? RepositoryManager::OVERRIDE | RepositoryManager::IGNORE_FILE_NOT_FOUND
            : 0;
        $repositoryPath = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);
        $pathReferences = $args->getArgument('file');

        $this->repoManager->addRootPathMapping(new PathMapping($repositoryPath, $pathReferences), $flags);

        return 0;
    }

    /**
     * Handles the "puli map --update" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleUpdate(Args $args)
    {
        $flags = $args->isOptionSet('force')
            ? RepositoryManager::OVERRIDE | RepositoryManager::IGNORE_FILE_NOT_FOUND
            : RepositoryManager::OVERRIDE;
        $repositoryPath = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);
        $mappingToUpdate = $this->repoManager->getRootPathMapping($repositoryPath);
        $pathReferences = array_flip($mappingToUpdate->getPathReferences());

        foreach ($args->getOption('add') as $pathReference) {
            $pathReferences[$pathReference] = true;
        }

        foreach ($args->getOption('remove') as $pathReference) {
            unset($pathReferences[$pathReference]);
        }

        if (0 === count($pathReferences)) {
            $this->repoManager->removeRootPathMapping($repositoryPath);

            return 0;
        }

        $updatedMapping = new PathMapping($repositoryPath, array_keys($pathReferences));

        if ($this->mappingsEqual($mappingToUpdate, $updatedMapping)) {
            throw new RuntimeException('Nothing to update.');
        }

        $this->repoManager->addRootPathMapping($updatedMapping, $flags);

        return 0;
    }

    /**
     * Handles the "puli map --delete" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleDelete(Args $args)
    {
        $repositoryPath = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);

        if (!$this->repoManager->hasRootPathMapping($repositoryPath)) {
            throw new RuntimeException(sprintf(
                'The path "%s" is not mapped in the package "%s".',
                $repositoryPath,
                $this->packages->getRootPackageName()
            ));
        }

        $this->repoManager->removeRootPathMapping($repositoryPath);

        return 0;
    }

    /**
     * Prints a list of path mappings.
     *
     * @param IO            $io       The I/O.
     * @param PathMapping[] $mappings The path mappings.
     * @param bool          $indent   Whether to indent the output.
     * @param bool          $enabled  Whether the path mappings are enabled. If
     *                                not, the output is printed in red.
     */
    private function printMappingTable(IO $io, array $mappings, $indent = false, $enabled = true)
    {
        $table = new Table(TableStyle::borderless());

        $pathTag = $enabled ? 'c1' : 'bad';

        foreach ($mappings as $mapping) {
            if ($enabled) {
                $pathReferences = array();

                foreach ($mapping->getPathReferences() as $pathReference) {
                    // Underline referenced packages
                    $pathReference = preg_replace('~^@([^:]+):~', '@<u>$1</u>:', $pathReference);

                    // Highlight path parts
                    $pathReference = preg_replace('~^(@([^:]+):)?(.*)$~', '$1<c2>$3</c2>', $pathReference);

                    $pathReferences[] = $pathReference;
                }

                $pathReferences = implode(', ', $pathReferences);
            } else {
                $pathReferences = '<bad>'.implode(', ', $mapping->getPathReferences()).'</bad>';
            }

            $table->addRow(array(
                "<$pathTag>{$mapping->getRepositoryPath()}</$pathTag>",
                $pathReferences
            ));
        }

        $table->render($io, $indent ? 4 : 0);
    }

    /**
     * Prints a list of conflicting path mappings.
     *
     * @param IO            $io       The I/O.
     * @param PathMapping[] $mappings The path mappings.
     * @param bool          $indent   Whether to indent the output.
     */
    private function printConflictTable(IO $io, array $mappings, $indent = false)
    {
        /** @var PathConflict[] $conflicts */
        $conflicts = array();
        $prefix = $indent ? '    ' : '';
        $printNewline = false;

        foreach ($mappings as $mapping) {
            foreach ($mapping->getConflicts() as $conflict) {
                $conflicts[spl_object_hash($conflict)] = $conflict;
            }
        }

        foreach ($conflicts as $conflict) {
            if ($printNewline) {
                $io->writeLine('');
            }

            $io->writeLine("$prefix<b>Conflict:</b> {$conflict->getRepositoryPath()}");
            $io->writeLine('');

            $table = new Table(TableStyle::borderless());

            foreach ($conflict->getMappings() as $mapping) {
                $table->addRow(array(
                    '<bad>'.$mapping->getContainingPackage()->getName().'</bad>',
                    '<bad>'.$mapping->getRepositoryPath().'</bad>',
                    '<bad>'.implode(', ', $mapping->getPathReferences()).'</bad>'
                ));
            }

            $io->writeLine("{$prefix}Mapped by:");
            $table->render($io, $indent ? 4 : 0);

            $printNewline = true;
        }
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

    /**
     * Returns the path mapping states selected in the console arguments.
     *
     * @param Args $args The console arguments.
     *
     * @return int[] The selected {@link PathMappingState} constants.
     */
    private function getPathMappingStates(Args $args)
    {
        $states = array();

        if ($args->isOptionSet('enabled')) {
            $states[] = PathMappingState::ENABLED;
        }

        if ($args->isOptionSet('not-found')) {
            $states[] = PathMappingState::NOT_FOUND;
        }

        if ($args->isOptionSet('conflict')) {
            $states[] = PathMappingState::CONFLICT;
        }

        return $states ?: PathMappingState::all();
    }

    /**
     * Prints the header for a path mapping state.
     *
     * @param IO  $io           The I/O.
     * @param int $pathMappingState The {@link PathMappingState} constant.
     */
    private function printPathMappingStateHeader(IO $io, $pathMappingState)
    {
        switch ($pathMappingState) {
            case PathMappingState::ENABLED:
                $io->writeLine('The following path mappings are currently enabled:');
                $io->writeLine('');
                return;
            case PathMappingState::NOT_FOUND:
                $io->writeLine('The target paths of the following path mappings were not found:');
                $io->writeLine('');
                return;
            case PathMappingState::CONFLICT:
                $io->writeLine('The following path mappings have conflicting paths:');
                $io->writeLine(' (add the package names to the "override-order" key in puli.json to resolve)');
                $io->writeLine('');
                return;
        }
    }

    private function mappingsEqual(PathMapping $mapping1, PathMapping $mapping2)
    {
        if ($mapping1->getRepositoryPath() !== $mapping2->getRepositoryPath()) {
            return false;
        }

        if ($mapping1->getPathReferences() !== $mapping2->getPathReferences()) {
            return false;
        }

        return true;
    }
}
