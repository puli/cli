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

use Puli\RepositoryManager\Api\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Api\Repository\RepositoryManager;
use Webmozart\Console\Api\Args\Args;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BuildHandler
{
    /**
     * @var RepositoryManager
     */
    private $repoManager;

    /**
     * @var DiscoveryManager
     */
    private $discoveryManager;

    public function __construct(RepositoryManager $repoManager, DiscoveryManager $discoveryManager)
    {
        $this->repoManager = $repoManager;
        $this->discoveryManager = $discoveryManager;
    }

    public function handle(Args $args)
    {
        if ($args->isOptionSet('force')) {
            $this->repoManager->clearRepository();
            $this->discoveryManager->clearDiscovery();
        }

        $this->repoManager->buildRepository();
        $this->discoveryManager->buildDiscovery();
    }
}
