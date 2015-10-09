<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Tests\Handler;

use PHPUnit_Framework_MockObject_MockObject;
use Puli\Cli\Handler\BuildCommandHandler;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\Repository\RepositoryManager;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BuildCommandHandlerTest extends AbstractCommandHandlerTest
{
    /**
     * @var Command
     */
    private static $buildCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|RepositoryManager
     */
    private $repoManager;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|FactoryManager
     */
    private $factoryManager;

    /**
     * @var BuildCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$buildCommand = self::$application->getCommand('build');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->repoManager = $this->getMock('Puli\Manager\Api\Repository\RepositoryManager');
        $this->discoveryManager = $this->getMock('Puli\Manager\Api\Discovery\DiscoveryManager');
        $this->factoryManager = $this->getMock('Puli\Manager\Api\Factory\FactoryManager');
        $this->handler = new BuildCommandHandler($this->repoManager, $this->discoveryManager, $this->factoryManager);
    }

    public function testBuild()
    {
        $args = self::$buildCommand->parseArgs(new StringArgs(''));

        $this->factoryManager->expects($this->once())
            ->method('autoGenerateFactoryClass');
        $this->repoManager->expects($this->at(0))
            ->method('clearRepository');
        $this->repoManager->expects($this->at(1))
            ->method('buildRepository');
        $this->discoveryManager->expects($this->at(0))
            ->method('clearDiscovery');
        $this->discoveryManager->expects($this->at(1))
            ->method('buildDiscovery');
        $this->discoveryManager->expects($this->at(2))
            ->method('removeObsoleteDisabledBindingDescriptors');

        $statusCode = $this->handler->handle($args);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testBuildAll()
    {
        $args = self::$buildCommand->parseArgs(new StringArgs('all'));

        $this->factoryManager->expects($this->once())
            ->method('autoGenerateFactoryClass');
        $this->repoManager->expects($this->at(0))
            ->method('clearRepository');
        $this->repoManager->expects($this->at(1))
            ->method('buildRepository');
        $this->discoveryManager->expects($this->at(0))
            ->method('clearDiscovery');
        $this->discoveryManager->expects($this->at(1))
            ->method('buildDiscovery');
        $this->discoveryManager->expects($this->at(2))
            ->method('removeObsoleteDisabledBindingDescriptors');

        $statusCode = $this->handler->handle($args);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testBuildRepository()
    {
        $args = self::$buildCommand->parseArgs(new StringArgs('repository'));

        $this->factoryManager->expects($this->never())
            ->method('autoGenerateFactoryClass');
        $this->repoManager->expects($this->at(0))
            ->method('clearRepository');
        $this->repoManager->expects($this->at(1))
            ->method('buildRepository');
        $this->discoveryManager->expects($this->never())
            ->method('clearDiscovery');
        $this->discoveryManager->expects($this->never())
            ->method('buildDiscovery');
        $this->discoveryManager->expects($this->never())
            ->method('removeObsoleteDisabledBindingDescriptors');

        $statusCode = $this->handler->handle($args);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testBuildDiscovery()
    {
        $args = self::$buildCommand->parseArgs(new StringArgs('discovery'));

        $this->factoryManager->expects($this->never())
            ->method('autoGenerateFactoryClass');
        $this->repoManager->expects($this->never())
            ->method('clearRepository');
        $this->repoManager->expects($this->never())
            ->method('buildRepository');
        $this->discoveryManager->expects($this->at(0))
            ->method('clearDiscovery');
        $this->discoveryManager->expects($this->at(1))
            ->method('buildDiscovery');
        $this->discoveryManager->expects($this->at(2))
            ->method('removeObsoleteDisabledBindingDescriptors');

        $statusCode = $this->handler->handle($args);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testBuildFactory()
    {
        $args = self::$buildCommand->parseArgs(new StringArgs('factory'));

        $this->factoryManager->expects($this->once())
            ->method('autoGenerateFactoryClass');
        $this->repoManager->expects($this->never())
            ->method('clearRepository');
        $this->repoManager->expects($this->never())
            ->method('buildRepository');
        $this->discoveryManager->expects($this->never())
            ->method('clearDiscovery');
        $this->discoveryManager->expects($this->never())
            ->method('buildDiscovery');
        $this->discoveryManager->expects($this->never())
            ->method('removeObsoleteDisabledBindingDescriptors');

        $statusCode = $this->handler->handle($args);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Invalid build target "foobar". Expected one of: "all", "factory", "repository", "discovery"
     */
    public function testBuildFailsIfInvalidTarget()
    {
        $args = self::$buildCommand->parseArgs(new StringArgs('foobar'));

        $this->factoryManager->expects($this->never())
            ->method('autoGenerateFactoryClass');
        $this->repoManager->expects($this->never())
            ->method('clearRepository');
        $this->repoManager->expects($this->never())
            ->method('buildRepository');
        $this->discoveryManager->expects($this->never())
            ->method('clearDiscovery');
        $this->discoveryManager->expects($this->never())
            ->method('buildDiscovery');
        $this->discoveryManager->expects($this->never())
            ->method('removeObsoleteDisabledBindingDescriptors');

        $this->handler->handle($args);
    }
}
