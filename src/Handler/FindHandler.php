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

use Puli\Discovery\Api\ResourceDiscovery;
use Puli\Repository\Api\ResourceRepository;
use Symfony\Component\Console\Helper\Table;
use Webmozart\Console\Adapter\IOOutput;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FindHandler
{
    /**
     * @var ResourceRepository
     */
    private $repo;

    /**
     * @var ResourceDiscovery
     */
    private $discovery;

    public function __construct(ResourceRepository $repo, ResourceDiscovery $discovery)
    {
        $this->repo = $repo;
        $this->discovery = $discovery;
    }

    public function handle(Args $args, IO $io)
    {
        $criteria = array();

        if ($args->isArgumentSet('pattern')) {
            $criteria['pattern'] = $args->getArgument('pattern');
        }

        if ($args->isOptionSet('type')) {
            $criteria['shortClass'] = $args->getOption('type');
        }

        if ($args->isOptionSet('bound-to')) {
            $criteria['bindingType'] = $args->getOption('bound-to');
        }

        if (!$criteria) {
            $io->errorLine('fatal: No search criteria specified.');

            return 1;
        }

        return $this->listMatches($io, $criteria);
    }

    private function listMatches(IO $io, array $criteria)
    {
        if (isset($criteria['pattern']) && isset($criteria['bindingType'])) {
            $matches = array_intersect_key(
                $this->findByPattern($criteria['pattern']),
                $this->findByBindingType($criteria['bindingType'])
            );
        } elseif (isset($criteria['pattern'])) {
            $matches = $this->findByPattern($criteria['pattern']);
        } elseif (isset($criteria['bindingType'])) {
            $matches = $this->findByBindingType($criteria['bindingType']);
        } else {
            $matches = $this->findByPattern('/*');
        }

        if (isset($criteria['shortClass'])) {
            $shortClass = $criteria['shortClass'];

            $matches = array_filter($matches, function ($value) use ($shortClass) {
                return $value === $shortClass;
            });
        }

        $this->printTable($io, $matches);

        return 0;
    }

    private function findByPattern($pattern)
    {
        $matches = array();

        if ('/' !== $pattern[0]) {
            $pattern = '/'.$pattern;
        }

        foreach ($this->repo->find($pattern) as $resource) {
            $matches[$resource->getPath()] = $this->getShortName(get_class($resource));
        }

        return $matches;
    }

    private function findByBindingType($typeName)
    {
        $matches = array();

        foreach ($this->discovery->find($typeName) as $binding) {
            foreach ($binding->getResources() as $resource) {
                $matches[$resource->getPath()] = $this->getShortName(get_class($resource));
            }
        }

        ksort($matches);

        return $matches;
    }

    private function printTable(IO $io, array $matches)
    {
        $table = new Table(new IOOutput($io));
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('');

        foreach ($matches as $path => $shortClass) {
            $table->addRow(array(
                $shortClass,
                " <em>$path</em>"
            ));
        }

        $table->render();
    }

    private function getShortName($className)
    {
        if (false !== ($pos = strrpos($className, '\\'))) {
            return substr($className, $pos + 1);
        }

        return $className;
    }
}
