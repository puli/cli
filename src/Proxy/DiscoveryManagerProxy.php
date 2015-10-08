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

use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Puli;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expression;

/**
 * Proxies a lazily fetched discovery manager.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryManagerProxy implements DiscoveryManager
{
    /**
     * @var Puli
     */
    private $puli;

    /**
     * Creates the proxy.
     *
     * @param Puli $puli The service locator to fetch the actual discovery
     *                   manager from.
     */
    public function __construct(Puli $puli)
    {
        $this->puli = $puli;
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->puli->getDiscoveryManager()->getContext();
    }

    /**
     * {@inheritdoc}
     */
    public function addRootTypeDescriptor(BindingTypeDescriptor $typeDescriptor, $flags = 0)
    {
        $this->puli->getDiscoveryManager()->addRootTypeDescriptor($typeDescriptor, $flags);
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootTypeDescriptor($typeName)
    {
        $this->puli->getDiscoveryManager()->removeRootTypeDescriptor($typeName);
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootTypeDescriptors(Expression $expr)
    {
        $this->puli->getDiscoveryManager()->removeRootTypeDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function clearRootTypeDescriptors()
    {
        $this->puli->getDiscoveryManager()->clearRootTypeDescriptors();
    }

    /**
     * {@inheritdoc}
     */
    public function getRootTypeDescriptor($typeName)
    {
        return $this->puli->getDiscoveryManager()->getRootTypeDescriptor($typeName);
    }

    /**
     * {@inheritdoc}
     */
    public function getRootTypeDescriptors()
    {
        return $this->puli->getDiscoveryManager()->getRootTypeDescriptors();
    }

    /**
     * {@inheritdoc}
     */
    public function findRootTypeDescriptors(Expression $expr)
    {
        return $this->puli->getDiscoveryManager()->findRootTypeDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootTypeDescriptor($typeName)
    {
        return $this->puli->getDiscoveryManager()->hasRootTypeDescriptor($typeName);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootTypeDescriptors(Expression $expr = null)
    {
        return $this->puli->getDiscoveryManager()->hasRootTypeDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeDescriptor($typeName, $packageName)
    {
        return $this->puli->getDiscoveryManager()->getTypeDescriptor($typeName, $packageName);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeDescriptors()
    {
        return $this->puli->getDiscoveryManager()->getTypeDescriptors();
    }

    /**
     * {@inheritdoc}
     */
    public function findTypeDescriptors(Expression $expr)
    {
        return $this->puli->getDiscoveryManager()->findTypeDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function hasTypeDescriptor($typeName, $packageName = null)
    {
        return $this->puli->getDiscoveryManager()->hasTypeDescriptor($typeName, $packageName);
    }

    /**
     * {@inheritdoc}
     */
    public function hasTypeDescriptors(Expression $expr = null)
    {
        return $this->puli->getDiscoveryManager()->hasTypeDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function addRootBindingDescriptor(BindingDescriptor $bindingDescriptor, $flags = 0)
    {
        $this->puli->getDiscoveryManager()->addRootBindingDescriptor($bindingDescriptor, $flags);
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootBindingDescriptor(Uuid $uuid)
    {
        $this->puli->getDiscoveryManager()->removeRootBindingDescriptor($uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootBindingDescriptors(Expression $expr)
    {
        $this->puli->getDiscoveryManager()->removeRootBindingDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function clearRootBindingDescriptors()
    {
        $this->puli->getDiscoveryManager()->clearRootBindingDescriptors();
    }

    /**
     * {@inheritdoc}
     */
    public function getRootBindingDescriptor(Uuid $uuid)
    {
        return $this->puli->getDiscoveryManager()->getRootBindingDescriptor($uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function getRootBindingDescriptors()
    {
        return $this->puli->getDiscoveryManager()->getRootBindingDescriptors();
    }

    /**
     * {@inheritdoc}
     */
    public function findRootBindingDescriptors(Expression $expr)
    {
        return $this->puli->getDiscoveryManager()->findRootBindingDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootBindingDescriptor(Uuid $uuid)
    {
        return $this->puli->getDiscoveryManager()->hasRootBindingDescriptor($uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootBindingDescriptors(Expression $expr = null)
    {
        return $this->puli->getDiscoveryManager()->hasRootBindingDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function enableBindingDescriptor(Uuid $uuid)
    {
        $this->puli->getDiscoveryManager()->enableBindingDescriptor($uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function disableBindingDescriptor(Uuid $uuid)
    {
        $this->puli->getDiscoveryManager()->disableBindingDescriptor($uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function removeObsoleteDisabledBindingDescriptors()
    {
        $this->puli->getDiscoveryManager()->removeObsoleteDisabledBindingDescriptors();
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingDescriptor(Uuid $uuid)
    {
        return $this->puli->getDiscoveryManager()->getBindingDescriptor($uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingDescriptors()
    {
        return $this->puli->getDiscoveryManager()->getBindingDescriptors();
    }

    /**
     * {@inheritdoc}
     */
    public function findBindingDescriptors(Expression $expr)
    {
        return $this->puli->getDiscoveryManager()->findBindingDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function hasBindingDescriptor(Uuid $uuid)
    {
        return $this->puli->getDiscoveryManager()->hasBindingDescriptor($uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function hasBindingDescriptors(Expression $expr = null)
    {
        return $this->puli->getDiscoveryManager()->hasBindingDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function buildDiscovery()
    {
        $this->puli->getDiscoveryManager()->buildDiscovery();
    }

    /**
     * {@inheritdoc}
     */
    public function clearDiscovery()
    {
        $this->puli->getDiscoveryManager()->clearDiscovery();
    }
}
