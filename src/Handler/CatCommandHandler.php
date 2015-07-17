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

use Puli\Repository\Api\Resource\BodyResource;
use Puli\Repository\Api\ResourceRepository;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\PathUtil\Path;

/**
 * Handles the "cat" command.
 *
 * @since  1.0
 *
 * @author Stephan Wentz <swentz@brainbits.net>
 */
class CatCommandHandler
{
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
     * Handles the "ls" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handle(Args $args, IO $io)
    {
        $path = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);

        $resources = $this->repo->find($path);

        if (!count($resources)) {
            $io->errorLine("No resources found for path $path");

            return 1;
        }

        foreach ($resources as $resource) {
            if ($resource instanceof BodyResource) {
                $io->writeLine($resource->getBody());
            }
        }

        return 0;
    }
}
