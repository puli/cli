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

use Puli\Repository\Api\Resource\Resource;
use Puli\Repository\Api\ResourceRepository;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\PathUtil\Path;

/**
 * Handles the "tree" command.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TreeCommandHandler
{
    /**
     * Prefix string for child resources.
     *
     * @internal
     */
    const CHILD_PREFIX = '├── ';

    /**
     * Prefix string for the last child resource of a parent resource.
     *
     * @internal
     */
    const LAST_CHILD_PREFIX = '└── ';

    /**
     * Prefix for nested resources when an ancestor resource is open.
     *
     * @internal
     */
    const NESTING_OPEN_PREFIX = '│   ';

    /**
     * Prefix for nested resources when no ancestor resource is open.
     *
     * @internal
     */
    const NESTING_CLOSED_PREFIX = '    ';

    /**
     * @var ResourceRepository
     */
    private $repo;

    /**
     * @var string
     */
    private $currentPath = '/';

    /**
     * Creates the handler.
     *
     * @param ResourceRepository $repo The resource repository.
     */
    public function __construct(ResourceRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Handles the "tree" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handle(Args $args, IO $io)
    {
        $path = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);

        $resource = $this->repo->get($path);
        $total = 0;

        $io->writeLine('<c1>'.$resource->getPath().'</c1>');

        $this->printTree($io, $resource, $total);

        $io->writeLine('');
        $io->writeLine($total.' resources');

        return 0;
    }

    /**
     * Recursively prints the tree for the given resource.
     *
     * @param IO       $io       The I/O.
     * @param Resource $resource The printed resource.
     * @param int      $total    Collects the total number of printed resources.
     * @param string   $prefix   The prefix for all printed resources.
     */
    private function printTree(IO $io, Resource $resource, &$total, $prefix = '')
    {
        // The root node has an empty name
        $children = $resource->listChildren();
        $lastIndex = count($children) - 1;
        $index = 0;

        foreach ($children as $child) {
            $isLastChild = $index === $lastIndex;
            $childPrefix = $isLastChild ? self::LAST_CHILD_PREFIX : self::CHILD_PREFIX;
            $nestingPrefix = $isLastChild ? self::NESTING_CLOSED_PREFIX : self::NESTING_OPEN_PREFIX;

            $name = $child->getName() ?: '/';

            if ($child->hasChildren()) {
                $name = '<c1>'.$name.'</c1>';
            }

            $io->writeLine($prefix.$childPrefix.$name);

            $this->printTree($io, $child, $total, $prefix.$nestingPrefix);

            ++$index;
            ++$total;
        }
    }
}
