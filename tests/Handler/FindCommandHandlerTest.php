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
use Puli\Cli\Handler\FindCommandHandler;
use Puli\Discovery\Api\Binding\BindingType;
use Puli\Discovery\Api\ResourceDiscovery;
use Puli\Discovery\Binding\EagerBinding;
use Puli\Repository\Api\ResourceRepository;
use Puli\Repository\Resource\Collection\ArrayResourceCollection;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;
use Puli\Repository\Resource\GenericResource;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FindCommandHandlerTest extends AbstractCommandHandlerTest
{
    /**
     * @var Command
     */
    private static $findCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ResourceRepository
     */
    private $repo;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ResourceDiscovery
     */
    private $discovery;

    /**
     * @var FindCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$findCommand = self::$application->getCommand('find');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->repo = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $this->discovery = $this->getMock('Puli\Discovery\Api\ResourceDiscovery');
        $this->handler = new FindCommandHandler($this->repo, $this->discovery);
    }

    public function testFindByRelativePattern()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('*pattern*'));

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/*pattern*')
            ->willReturn(new ArrayResourceCollection(array(
                new FileResource(__FILE__, '/path/file'),
                new GenericResource('/path/resource1'),
                new GenericResource('/path/resource2'),
            )));
        $this->discovery->expects($this->never())
            ->method('find');

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
FileResource    /path/file
GenericResource /path/resource1
GenericResource /path/resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testFindByAbsolutePattern()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('/*pattern*'));

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/*pattern*')
            ->willReturn(new ArrayResourceCollection(array(
                new FileResource(__FILE__, '/path/file'),
                new GenericResource('/path/resource1'),
                new GenericResource('/path/resource2'),
            )));
        $this->discovery->expects($this->never())
            ->method('find');

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
FileResource    /path/file
GenericResource /path/resource1
GenericResource /path/resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testFindByType()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--type GenericResource'));

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/*')
            ->willReturn(new ArrayResourceCollection(array(
                new GenericResource('/path/resource1'),
                new FileResource(__FILE__, '/path/file'),
                new GenericResource('/path/resource2'),
            )));
        $this->discovery->expects($this->never())
            ->method('find');

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
GenericResource /path/resource1
GenericResource /path/resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testFindByBindingType()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--bound-to vendor/type'));
        $type = new BindingType('vendor/type');

        $this->repo->expects($this->never())
            ->method('find');
        $this->discovery->expects($this->once())
            ->method('find')
            ->with('vendor/type')
            ->willReturn(array(
                new EagerBinding('/path', new ArrayResourceCollection(array(
                    new GenericResource('/path/resource1'),
                    new FileResource(__FILE__, '/path/file'),
                )), $type),
                new EagerBinding('/path', new ArrayResourceCollection(array(
                    new GenericResource('/path/resource2'),
                )), $type),
            ));

        $statusCode = $this->handler->handle($args, $this->io);

        // Result is sorted by path
        $expected = <<<EOF
FileResource    /path/file
GenericResource /path/resource1
GenericResource /path/resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testFindByPatternAndType()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('*pattern* --type GenericResource'));

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/*pattern*')
            ->willReturn(new ArrayResourceCollection(array(
                new FileResource(__FILE__, '/path/file'),
                new GenericResource('/path/resource1'),
                new GenericResource('/path/resource2'),
            )));
        $this->discovery->expects($this->never())
            ->method('find');

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
GenericResource /path/resource1
GenericResource /path/resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testFindByBindingTypeAndType()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--bound-to vendor/type --type GenericResource'));
        $type = new BindingType('vendor/type');

        $this->repo->expects($this->never())
            ->method('find');
        $this->discovery->expects($this->once())
            ->method('find')
            ->with('vendor/type')
            ->willReturn(array(
                new EagerBinding('/path', new ArrayResourceCollection(array(
                    new GenericResource('/path/resource1'),
                    new FileResource(__FILE__, '/path/file'),
                )), $type),
                new EagerBinding('/path', new ArrayResourceCollection(array(
                    new GenericResource('/path/resource2'),
                )), $type),
            ));

        $statusCode = $this->handler->handle($args, $this->io);

        // Result is sorted by path
        $expected = <<<EOF
GenericResource /path/resource1
GenericResource /path/resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testFindByPatternAndBindingType()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('*pattern* --bound-to vendor/type'));
        $type = new BindingType('vendor/type');

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/*pattern*')
            ->willReturn(new ArrayResourceCollection(array(
                new FileResource(__FILE__, '/path/file'),
                new DirectoryResource(__DIR__, '/path/dir'),
                new GenericResource('/path/resource1'),
                new GenericResource('/path/resource2'),
                new GenericResource('/path/resource3'),
            )));
        $this->discovery->expects($this->once())
            ->method('find')
            ->with('vendor/type')
            ->willReturn(array(
                new EagerBinding('/path', new ArrayResourceCollection(array(
                    new GenericResource('/path/resource1'),
                    new GenericResource('/path/resource4'),
                    new FileResource(__FILE__, '/path/file'),
                )), $type),
                new EagerBinding('/path', new ArrayResourceCollection(array(
                    new GenericResource('/path/resource2'),
                    new GenericResource('/path/resource5'),
                )), $type),
            ));

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
FileResource    /path/file
GenericResource /path/resource1
GenericResource /path/resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No search criteria specified
     */
    public function testFailIfNoCriteria()
    {
        $args = self::$findCommand->parseArgs(new StringArgs(''));

        $this->repo->expects($this->never())
            ->method('find');
        $this->discovery->expects($this->never())
            ->method('find');

        $this->handler->handle($args, $this->io);
    }
}
