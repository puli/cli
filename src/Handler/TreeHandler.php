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
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TreeHandler
{
    const CHILD_PREFIX = '├── ';

    const LAST_CHILD_PREFIX = '└── ';

    const NESTING_OPEN_PREFIX = '│   ';

    const NESTING_CLOSED_PREFIX = '    ';

    /**
     * @var ResourceRepository
     */
    private $repo;

    private $currentPath = '/';

    public function __construct(ResourceRepository $repo)
    {
        $this->repo = $repo;
    }

    public function handle(Args $args, IO $io)
    {
        $path = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);

        $resource = $this->repo->get($path);
        $total = 0;

        $io->writeLine('<em>'.$resource->getPath().'</em>');

        $this->printTree($io, $resource, $total);

        $io->writeLine('');
        $io->writeLine($total.' child resources');

        return 0;
    }

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
                $name = '<em>'.$name.'</em>';
            }

            $io->writeLine($prefix.$childPrefix.$name);

            $this->printTree($io, $child, $total, $prefix.$nestingPrefix);

            ++$index;
            ++$total;
        }
    }

}
