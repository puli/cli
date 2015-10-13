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

use Puli\Cli\Style\PuliTableStyle;
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
use Webmozart\Expression\Expr;
use Webmozart\PathUtil\Path;

/**
 * Handles the "puli map" command.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MapCommandHandler
{
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
        $indentation = ($printState && $printPackageName) ? 8
            : ($printState || $printPackageName ? 4 : 0);

        foreach ($states as $state) {
            $statePrinted = !$printState;

            if (PathMappingState::CONFLICT === $state) {
                $expr = Expr::method('getContainingPackage', Expr::method('getName', Expr::in($packageNames)))
                    ->andMethod('getState', Expr::same($state));

                $mappings = $this->repoManager->findPathMappings($expr);

                if (!$mappings) {
                    continue;
                }

                $printAdvice = false;

                if ($printState) {
                    $this->printPathMappingStateHeader($io, $state);
                }

                $this->printConflictTable($io, $mappings, $printState ? 4 : 0);

                if ($printHeaders) {
                    $io->writeLine('');
                }

                continue;
            }

            foreach ($packageNames as $packageName) {
                $expr = Expr::method('getContainingPackage', Expr::method('getName', Expr::same($packageName)))
                    ->andMethod('getState', Expr::same($state));

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
                    $io->writeLine(sprintf('%sPackage: %s', $prefix, $packageName));
                    $io->writeLine('');
                }

                $this->printMappingTable($io, $mappings, $indentation, PathMappingState::ENABLED === $state);

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
     * @param IO            $io          The I/O.
     * @param PathMapping[] $mappings    The path mappings.
     * @param int           $indentation The number of spaces to indent the
     *                                   output.
     * @param bool          $enabled     Whether the path mappings are enabled.
     *                                   If not, the output is printed in red.
     */
    private function printMappingTable(IO $io, array $mappings, $indentation = 0, $enabled = true)
    {
        $table = new Table(PuliTableStyle::borderless());

        $table->setHeaderRow(array('Puli Path', 'Real Path(s)'));

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
                sprintf('<%s>%s</%s>', $pathTag, $mapping->getRepositoryPath(), $pathTag),
                $pathReferences,
            ));
        }

        $table->render($io, $indentation);
    }

    /**
     * Prints a list of conflicting path mappings.
     *
     * @param IO            $io          The I/O.
     * @param PathMapping[] $mappings    The path mappings.
     * @param int           $indentation The number of spaces to indent the
     *                                   output.
     */
    private function printConflictTable(IO $io, array $mappings, $indentation = 0)
    {
        /** @var PathConflict[] $conflicts */
        $conflicts = array();
        $shortPrefix = str_repeat(' ', $indentation);
        $prefix = str_repeat(' ', $indentation + 4);
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

            $io->writeLine(sprintf('%sConflicting path: %s', $shortPrefix, $conflict->getRepositoryPath()));
            $io->writeLine('');

            $table = new Table(PuliTableStyle::borderless());

            $table->setHeaderRow(array('Package', 'Puli Path', 'Real Path(s)'));

            foreach ($conflict->getMappings() as $mapping) {
                $table->addRow(array(
                    '<bad>'.$mapping->getContainingPackage()->getName().'</bad>',
                    '<bad>'.$mapping->getRepositoryPath().'</bad>',
                    '<bad>'.implode(', ', $mapping->getPathReferences()).'</bad>',
                ));
            }

            $io->writeLine(sprintf('%sMapped by the following mappings:', $prefix));
            $io->writeLine('');

            $table->render($io, $indentation + 4);

            $printNewline = true;
        }
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
        $states = array(
            PathMappingState::ENABLED => 'enabled',
            PathMappingState::NOT_FOUND => 'not-found',
            PathMappingState::CONFLICT => 'conflict',
        );

        $states = array_filter($states, function ($option) use ($args) {
            return $args->isOptionSet($option);
        });

        return array_keys($states) ?: PathMappingState::all();
    }

    /**
     * Prints the header for a path mapping state.
     *
     * @param IO  $io               The I/O.
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
                $io->writeLine('Some path mappings have conflicting paths:');
                $io->writeLine(' (add the package names to the "override-order" key in puli.json to resolve)');
                $io->writeLine('');

                return;
        }
    }

    private function mappingsEqual(PathMapping $mapping1, PathMapping $mapping2)
    {
        return $mapping1->getRepositoryPath() === $mapping2->getRepositoryPath() &&
            $mapping1->getPathReferences() === $mapping2->getPathReferences();
    }
}
