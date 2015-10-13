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

use Puli\Cli\Util\StringUtil;
use Puli\Discovery\Api\Discovery;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Repository\Api\ResourceRepository;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Console\UI\Style\TableStyle;
use Webmozart\Expression\Expr;

/**
 * Handles the "find" command.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FindCommandHandler
{
    /**
     * @var ResourceRepository
     */
    private $repo;

    /**
     * @var Discovery
     */
    private $discovery;

    /**
     * Creates the handler.
     *
     * @param ResourceRepository $repo      The resource repository.
     * @param Discovery          $discovery The discovery.
     */
    public function __construct(ResourceRepository $repo, Discovery $discovery)
    {
        $this->repo = $repo;
        $this->discovery = $discovery;
    }

    /**
     * Handles the "find" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handle(Args $args, IO $io)
    {
        $criteria = array();

        if ($args->isOptionSet('path')) {
            $criteria['path'] = $args->getOption('path');
            $criteria['language'] = $args->getOption('language');
        }

        if ($args->isOptionSet('name')) {
            if (isset($criteria['path'])) {
                throw new RuntimeException('The options --name and --path cannot be combined.');
            }

            $criteria['path'] = '/**/'.$args->getOption('name');
            $criteria['language'] = $args->getOption('language');
        }

        if ($args->isOptionSet('class')) {
            $criteria['class'] = $args->getOption('class');
        }

        if ($args->isOptionSet('type')) {
            $criteria['bindingType'] = $args->getOption('type');
        }

        if (empty($criteria)) {
            throw new RuntimeException('No search criteria specified.');
        }

        return $this->listMatches($io, $criteria);
    }

    /**
     * Lists the matches for the given search criteria.
     *
     * @param IO    $io       The I/O.
     * @param array $criteria The array with the optional keys "pattern",
     *                        "shortClass" and "bindingType".
     *
     * @return int The status code.
     */
    private function listMatches(IO $io, array $criteria)
    {
        if (isset($criteria['path']) && isset($criteria['bindingType'])) {
            $matches = array_intersect_key(
                $this->findByPath($criteria['path'], $criteria['language']),
                $this->findByBindingType($criteria['bindingType'])
            );
        } elseif (isset($criteria['path'])) {
            $matches = $this->findByPath($criteria['path'], $criteria['language']);
        } elseif (isset($criteria['bindingType'])) {
            $matches = $this->findByBindingType($criteria['bindingType']);
        } else {
            $matches = $this->findByPath('/*');
        }

        if (isset($criteria['class'])) {
            $shortClass = $criteria['class'];

            $matches = array_filter($matches, function ($value) use ($shortClass) {
                return $value === $shortClass;
            });
        }

        $this->printTable($io, $matches);

        return 0;
    }

    /**
     * Finds the resources for a given path pattern.
     *
     * @param string $query    The resource query.
     * @param string $language The language of the query.
     *
     * @return string[] An array of short resource class names indexed by
     *                  the resource path.
     */
    private function findByPath($query, $language = 'glob')
    {
        $matches = array();
        $query = '/'.ltrim($query, '/');

        foreach ($this->repo->find($query, $language) as $resource) {
            $matches[$resource->getPath()] = StringUtil::getShortClassName(get_class($resource));
        }

        return $matches;
    }

    /**
     * Finds the resources for a given binding type.
     *
     * @param string $typeName The type name.
     *
     * @return string[] An array of short resource class names indexed by
     *                  the resource path.
     */
    private function findByBindingType($typeName)
    {
        $matches = array();

        $expr = Expr::isInstanceOf('Puli\Discovery\Binding\ResourceBinding');

        foreach ($this->discovery->findBindings($typeName, $expr) as $binding) {
            /** @var ResourceBinding $binding */
            foreach ($binding->getResources() as $resource) {
                $matches[$resource->getPath()] = StringUtil::getShortClassName(get_class($resource));
            }
        }

        ksort($matches);

        return $matches;
    }

    /**
     * Prints the given resources.
     *
     * @param IO       $io      The I/O.
     * @param string[] $matches An array of short resource class names indexed
     *                          by the resource path.
     */
    private function printTable(IO $io, array $matches)
    {
        $table = new Table(TableStyle::borderless());

        foreach ($matches as $path => $shortClass) {
            $table->addRow(array($shortClass, sprintf('<c1>%s</c1>', $path)));
        }

        $table->render($io);
    }
}
