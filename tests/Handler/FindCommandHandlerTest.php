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
use Puli\Discovery\Api\Discovery;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Repository\Api\ResourceRepository;
use Puli\Repository\Resource\Collection\ArrayResourceCollection;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;
use Puli\Repository\Resource\GenericResource;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 *
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
     * @var PHPUnit_Framework_MockObject_MockObject|Discovery
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
        $this->discovery = $this->getMock('Puli\Discovery\Api\Discovery');
        $this->handler = new FindCommandHandler($this->repo, $this->discovery);
    }

    public function testFindByRelativePath()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--path *pattern*'));

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/*pattern*')
            ->willReturn(new ArrayResourceCollection(array(
                new FileResource(__FILE__, '/path/file'),
                new GenericResource('/path/resource1'),
                new GenericResource('/path/resource2'),
            )));
        $this->discovery->expects($this->never())
            ->method('findByType');

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

    public function testFindByAbsolutePath()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--path /*pattern*'));

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/*pattern*')
            ->willReturn(new ArrayResourceCollection(array(
                new FileResource(__FILE__, '/path/file'),
                new GenericResource('/path/resource1'),
                new GenericResource('/path/resource2'),
            )));
        $this->discovery->expects($this->never())
            ->method('findByType');

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

    public function testFindByPathAndLanguage()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--path *pattern* --language xpath'));

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/*pattern*', 'xpath')
            ->willReturn(new ArrayResourceCollection(array(
                new FileResource(__FILE__, '/path/file'),
                new GenericResource('/path/resource1'),
                new GenericResource('/path/resource2'),
            )));
        $this->discovery->expects($this->never())
            ->method('findByType');

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

    public function testFindByName()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--name *.ext'));

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/**/*.ext')
            ->willReturn(new ArrayResourceCollection(array(
                new FileResource(__FILE__, '/path/file'),
                new GenericResource('/path/resource1'),
                new GenericResource('/path/resource2'),
            )));
        $this->discovery->expects($this->never())
            ->method('findByType');

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
     */
    public function testFindFailsIfPassingNameAndPath()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--name *.ext --path /**/*.ext'));

        $this->repo->expects($this->never())
            ->method('find');

        $this->handler->handle($args, $this->io);
    }

    public function testFindByClass()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--class GenericResource'));

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/*')
            ->willReturn(new ArrayResourceCollection(array(
                new GenericResource('/path/resource1'),
                new FileResource(__FILE__, '/path/file'),
                new GenericResource('/path/resource2'),
            )));
        $this->discovery->expects($this->never())
            ->method('findByType');

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
        $args = self::$findCommand->parseArgs(new StringArgs('--type vendor/type'));

        $binding1 = new ResourceBinding('/path1', 'vendor/type');
        $binding2 = new ResourceBinding('/path2', 'vendor/type');
        $binding1->setRepository($this->repo);
        $binding2->setRepository($this->repo);

        $this->repo->expects($this->at(0))
            ->method('find')
            ->with('/path1')
            ->willReturn(new ArrayResourceCollection(array(
                new GenericResource('/path1/resource1'),
                new FileResource(__FILE__, '/path1/file'),
            )));
        $this->repo->expects($this->at(1))
            ->method('find')
            ->with('/path2')
            ->willReturn(new ArrayResourceCollection(array(
                new GenericResource('/path2/resource2'),
            )));
        $this->discovery->expects($this->once())
            ->method('findBindings')
            ->with('vendor/type', Expr::isInstanceOf('Puli\Discovery\Binding\ResourceBinding'))
            ->willReturn(array($binding1, $binding2));

        $statusCode = $this->handler->handle($args, $this->io);

        // Result is sorted by path
        $expected = <<<EOF
FileResource    /path1/file
GenericResource /path1/resource1
GenericResource /path2/resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testFindByPathAndClass()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--path *pattern* --class GenericResource'));

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/*pattern*')
            ->willReturn(new ArrayResourceCollection(array(
                new FileResource(__FILE__, '/path/file'),
                new GenericResource('/path/resource1'),
                new GenericResource('/path/resource2'),
            )));
        $this->discovery->expects($this->never())
            ->method('findByType');

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
GenericResource /path/resource1
GenericResource /path/resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testFindByBindingTypeAndClass()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--type vendor/type --class GenericResource'));

        $binding1 = new ResourceBinding('/path1', 'vendor/type');
        $binding2 = new ResourceBinding('/path2', 'vendor/type');
        $binding1->setRepository($this->repo);
        $binding2->setRepository($this->repo);

        $this->repo->expects($this->at(0))
            ->method('find')
            ->with('/path1')
            ->willReturn(new ArrayResourceCollection(array(
                new GenericResource('/path1/resource1'),
                new FileResource(__FILE__, '/path1/file'),
            )));
        $this->repo->expects($this->at(1))
            ->method('find')
            ->with('/path2')
            ->willReturn(new ArrayResourceCollection(array(
                new GenericResource('/path2/resource2'),
            )));
        $this->discovery->expects($this->once())
            ->method('findBindings')
            ->with('vendor/type', Expr::isInstanceOf('Puli\Discovery\Binding\ResourceBinding'))
            ->willReturn(array($binding1, $binding2));

        $statusCode = $this->handler->handle($args, $this->io);

        // Result is sorted by path
        $expected = <<<EOF
GenericResource /path1/resource1
GenericResource /path2/resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testFindByPathAndBindingType()
    {
        $args = self::$findCommand->parseArgs(new StringArgs('--path *pattern* --language xpath --type vendor/type'));
        $type = new BindingType('vendor/type');

        $binding1 = new ResourceBinding('/path1', 'vendor/type');
        $binding2 = new ResourceBinding('/path2', 'vendor/type');
        $binding1->setRepository($this->repo);
        $binding2->setRepository($this->repo);

        $this->repo->expects($this->at(0))
            ->method('find')
            ->with('/*pattern*', 'xpath')
            ->willReturn(new ArrayResourceCollection(array(
                new FileResource(__FILE__, '/path/file'),
                new DirectoryResource(__DIR__, '/path/dir'),
                new GenericResource('/path/resource1'),
                new GenericResource('/path/resource2'),
                new GenericResource('/path/resource3'),
            )));
        $this->repo->expects($this->at(1))
            ->method('find')
            ->with('/path1')
            ->willReturn(new ArrayResourceCollection(array(
                new GenericResource('/path/resource1'),
                new GenericResource('/path/resource4'),
                new FileResource(__FILE__, '/path/file'),
            )));
        $this->repo->expects($this->at(2))
            ->method('find')
            ->with('/path2')
            ->willReturn(new ArrayResourceCollection(array(
                new GenericResource('/path/resource2'),
                new GenericResource('/path/resource5'),
            )));

        $this->discovery->expects($this->once())
            ->method('findBindings')
            ->with('vendor/type', Expr::isInstanceOf('Puli\Discovery\Binding\ResourceBinding'))
            ->willReturn(array($binding1, $binding2));

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
            ->method('findByType');

        $this->handler->handle($args, $this->io);
    }
}
