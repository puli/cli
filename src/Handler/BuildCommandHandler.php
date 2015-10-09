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
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\Repository\RepositoryManager;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;

/**
 * Handles the "build" command.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BuildCommandHandler
{
    /**
     * @var string[]
     */
    private static $targets = array('all', 'factory', 'repository', 'discovery');

    /**
     * @var RepositoryManager
     */
    private $repoManager;

    /**
     * @var DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var FactoryManager
     */
    private $factoryManager;

    /**
     * Creates the handler.
     *
     * @param RepositoryManager $repoManager      The repository manager.
     * @param DiscoveryManager  $discoveryManager The discovery manager.
     * @param FactoryManager    $factoryManager   The factory manager.
     */
    public function __construct(RepositoryManager $repoManager, DiscoveryManager $discoveryManager, FactoryManager $factoryManager)
    {
        $this->repoManager = $repoManager;
        $this->discoveryManager = $discoveryManager;
        $this->factoryManager = $factoryManager;
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

        if ('all' === $target || 'factory' === $target) {
            $this->factoryManager->autoGenerateFactoryClass();
        }

        if ('all' === $target || 'repository' === $target) {
            $this->repoManager->clearRepository();
            $this->repoManager->buildRepository();
        }

        if ('all' === $target || 'discovery' === $target) {
            $this->discoveryManager->clearDiscovery();
            $this->discoveryManager->buildDiscovery();
            $this->discoveryManager->removeObsoleteDisabledBindingDescriptors();
        }

        return 0;
    }
}
