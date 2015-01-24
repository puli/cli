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

use Puli\Repository\Api\Resource\Resource;
use Puli\RepositoryManager\Puli;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TreeCommand extends Command
{
    const CHILD_PREFIX = '├── ';

    const LAST_CHILD_PREFIX = '└── ';

    const NESTING_OPEN_PREFIX = '│   ';

    const NESTING_CLOSED_PREFIX = '    ';

    private $currentPath = '/';

    protected function configure()
    {
        $this
            ->setName('tree')
            ->setDescription('Print the contents of a resource as tree')
            ->addArgument('path', InputArgument::OPTIONAL, 'The path of a resource', '/')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $puli = new Puli(getcwd());
        $environment = $puli->getEnvironment();
        $repo = $environment->getRepository();
        $path = Path::makeAbsolute($input->getArgument('path'), $this->currentPath);

        $resource = $repo->get($path);
        $total = 0;

        $output->writeln('<em>'.$resource->getPath().'</em>');

        $this->printTree($output, $resource, $total);

        $output->writeln('');
        $output->writeln($total.' child resources');

        return 0;
    }

    private function printTree(OutputInterface $output, Resource $resource, &$total, $prefix = '')
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

            $output->writeln($prefix.$childPrefix.$name);

            $this->printTree($output, $child, $total, $prefix.$nestingPrefix);

            ++$index;
            ++$total;
        }
    }
}
