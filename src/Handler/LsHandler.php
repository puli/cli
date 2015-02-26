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

use Puli\Repository\Api\ResourceCollection;
use Puli\Repository\Api\ResourceRepository;
use Webmozart\Console\Adapter\IOOutput;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\Rendering\Dimensions;
use Webmozart\Console\Rendering\Element\WrappedGrid;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LsHandler
{
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

        if (!$resource->hasChildren()) {
            $io->errorLine(sprintf(
                'fatal: The resource "%s" does not have children.',
                $resource->getPath()
            ));

            return 1;
        }

        $this->listShort($io, $resource->listChildren());

        return 0;
    }

    private function listShort(IO $io, ResourceCollection $resources)
    {
        $dimensions = Dimensions::forCurrentWindow();
        $grid = new WrappedGrid($dimensions->getWidth());
        $grid->setHorizontalSeparator('  ');

        foreach ($resources as $resource) {
            $name = $resource->getName();

            if ($resource->hasChildren()) {
                $name = '<em>'.$name.'</em>';
            }

            $grid->addCell($name);
        }

        $grid->render(new IOOutput($io));
    }
}
