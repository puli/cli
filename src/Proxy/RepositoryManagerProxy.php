<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Proxy;

use Puli\Manager\Api\Container;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Api\Repository\RepositoryManager;
use Webmozart\Expression\Expression;

/**
 * Proxies a lazily fetched repository manager.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryManagerProxy implements RepositoryManager
{
    /**
     * @var Container
     */
    private $puli;

    /**
     * Creates the proxy.
     *
     * @param Container $puli The service locator to fetch the actual repository
     *                        manager from
     */
    public function __construct(Container $puli)
    {
        $this->puli = $puli;
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->puli->getRepositoryManager()->getContext();
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository()
    {
        return $this->puli->getRepositoryManager()->getRepository();
    }

    /**
     * {@inheritdoc}
     */
    public function addRootPathMapping(PathMapping $mapping, $flags = 0)
    {
        $this->puli->getRepositoryManager()->addRootPathMapping($mapping, $flags);
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootPathMapping($repositoryPath)
    {
        $this->puli->getRepositoryManager()->removeRootPathMapping($repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootPathMappings(Expression $expr)
    {
        $this->puli->getRepositoryManager()->removeRootPathMappings($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function clearRootPathMappings()
    {
        $this->puli->getRepositoryManager()->clearRootPathMappings();
    }

    /**
     * {@inheritdoc}
     */
    public function getRootPathMapping($repositoryPath)
    {
        return $this->puli->getRepositoryManager()->getRootPathMapping($repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function findRootPathMappings(Expression $expr)
    {
        return $this->puli->getRepositoryManager()->findRootPathMappings($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function getRootPathMappings()
    {
        return $this->puli->getRepositoryManager()->getRootPathMappings();
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootPathMapping($repositoryPath)
    {
        return $this->puli->getRepositoryManager()->hasRootPathMapping($repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootPathMappings(Expression $expr = null)
    {
        return $this->puli->getRepositoryManager()->hasRootPathMappings($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function getPathMapping($repositoryPath, $packageName)
    {
        return $this->puli->getRepositoryManager()->getPathMapping($repositoryPath, $packageName);
    }

    /**
     * {@inheritdoc}
     */
    public function getPathMappings()
    {
        return $this->puli->getRepositoryManager()->getPathMappings();
    }

    /**
     * {@inheritdoc}
     */
    public function findPathMappings(Expression $expr)
    {
        return $this->puli->getRepositoryManager()->findPathMappings($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function hasPathMapping($repositoryPath, $packageName)
    {
        return $this->puli->getRepositoryManager()->hasPathMapping($repositoryPath, $packageName);
    }

    /**
     * {@inheritdoc}
     */
    public function hasPathMappings(Expression $expr = null)
    {
        return $this->puli->getRepositoryManager()->hasPathMappings($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function getPathConflicts()
    {
        return $this->puli->getRepositoryManager()->getPathConflicts();
    }

    /**
     * {@inheritdoc}
     */
    public function buildRepository()
    {
        $this->puli->getRepositoryManager()->buildRepository();
    }

    /**
     * {@inheritdoc}
     */
    public function clearRepository()
    {
        $this->puli->getRepositoryManager()->clearRepository();
    }
}
