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
use Puli\RepositoryManager\ManagerFactory;
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
        $factory = new ManagerFactory();
        $environment = $factory->createProjectEnvironment(getcwd());
        $repo = $environment->getRepository();
        $path = Path::makeAbsolute($input->getArgument('path'), $this->currentPath);

        $total = 0;

        $this->printTree($output, $repo->get($path), $total);

        $output->writeln('');
        $output->writeln($total.' resources');

        return 0;
    }

    private function printTree(OutputInterface $output, Resource $resource, &$total, $prefix = '')
    {
        $name = $resource->getName();
        $children = $resource->listChildren();
        $lastIndex = count($children) - 1;
        $index = 0;

        if ($resource->hasChildren()) {
            $name = '<em>'.$name.'</em>';
        }

        $output->writeln($name);

        foreach ($children as $child) {
            $isLastChild = $index === $lastIndex;
            $childPrefix = $isLastChild ? self::LAST_CHILD_PREFIX : self::CHILD_PREFIX;
            $nestingPrefix = $isLastChild ? self::NESTING_CLOSED_PREFIX : self::NESTING_OPEN_PREFIX;

            $output->write($prefix.$childPrefix);

            $this->printTree($output, $child, $total, $prefix.$nestingPrefix);

            ++$index;
            ++$total;
        }
    }
}
