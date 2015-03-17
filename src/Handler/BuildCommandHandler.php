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

use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Repository\RepositoryManager;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;

/**
 * Handles the "build" command.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BuildCommandHandler
{
    /**
     * @var string[]
     */
    private static $targets = array('all', 'repository', 'discovery');

    /**
     * @var RepositoryManager
     */
    private $repoManager;

    /**
     * @var DiscoveryManager
     */
    private $discoveryManager;

    /**
     * Creates the handler.
     *
     * @param RepositoryManager $repoManager      The repository manager.
     * @param DiscoveryManager  $discoveryManager The discovery manager.
     */
    public function __construct(RepositoryManager $repoManager, DiscoveryManager $discoveryManager)
    {
        $this->repoManager = $repoManager;
        $this->discoveryManager = $discoveryManager;
    }

    /**
     * Handles the "build" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handle(Args $args)
    {
        $target = $args->getArgument('target');

        if (!in_array($target, self::$targets)) {
            throw new RuntimeException(sprintf(
                'Invalid build target "%s". Expected one of: "%s"',
                $target,
                implode('", "', self::$targets)
            ));
        }

        if ('all' === $target || 'repository' === $target) {
            if ($args->isOptionSet('force')) {
                $this->repoManager->clearRepository();
            }

            $this->repoManager->buildRepository();
        }

        if ('all' === $target || 'discovery' === $target) {
            if ($args->isOptionSet('force')) {
                $this->discoveryManager->clearDiscovery();
            }

            $this->discoveryManager->buildDiscovery();
        }

        return 0;
    }
}
