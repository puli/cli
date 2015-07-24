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

use Puli\Repository\Api\ResourceRepository;
use Puli\UrlGenerator\Api\UrlGenerator;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;

/**
 * Handles the "url" command.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UrlCommandHandler
{
    /**
     * @var UrlGenerator
     */
    private $urlGenerator;

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
     * @param UrlGenerator       $urlGenerator The URL generator.
     * @param ResourceRepository $repo         The resource repository.
     */
    public function __construct(UrlGenerator $urlGenerator, ResourceRepository $repo)
    {
        $this->urlGenerator = $urlGenerator;
        $this->repo = $repo;
    }

    /**
     * Handles the "url" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handle(Args $args, IO $io)
    {
        foreach ($args->getArgument('path') as $path) {
            if (Glob::isDynamic($path)) {
                foreach ($this->repo->find($path) as $resource) {
                    $this->printUrl($resource->getPath(), $io);
                }
            } else {
                $this->printUrl($path, $io);
            }
        }

        return 0;
    }

    /**
     * Prints the URL of a Puli path.
     *
     * @param string $path A Puli path.
     * @param IO     $io   The I/O.
     */
    private function printUrl($path, IO $io)
    {
        $path = Path::makeAbsolute($path, $this->currentPath);
        $io->writeLine($this->urlGenerator->generateUrl($path));
    }
}
