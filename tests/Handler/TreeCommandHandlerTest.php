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

use Puli\Cli\Handler\TreeCommandHandler;
use Puli\Repository\InMemoryRepository;
use Puli\Repository\Tests\Resource\TestDirectory;
use Puli\Repository\Tests\Resource\TestFile;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TreeCommandHandlerTest extends AbstractCommandHandlerTest
{
    /**
     * @var Command
     */
    private static $treeCommand;

    /**
     * @var InMemoryRepository
     */
    private $repo;

    /**
     * @var TreeCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$treeCommand = self::$application->getCommand('tree');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->repo = new InMemoryRepository();
        $this->handler = new TreeCommandHandler($this->repo);
    }

    public function testPrintRootTree()
    {
        $args = self::$treeCommand->parseArgs(new StringArgs(''));

        $this->repo->add('/app', new TestDirectory('/app', array(
            new TestDirectory('/app/dir1', array(
                new TestFile('/app/dir1/file1'),
            )),
            new TestDirectory('/app/dir2', array(
                new TestFile('/app/dir2/file1'),
                new TestFile('/app/dir2/file2'),
            )),
            new TestFile('/app/file'),
            new TestFile('/app/resource1'),
            new TestFile('/app/resource2'),
        )));

        $expected = <<<EOF
/
└── app
    ├── dir1
    │   └── file1
    ├── dir2
    │   ├── file1
    │   └── file2
    ├── file
    ├── resource1
    └── resource2

9 resources

EOF;

        $this->assertSame(0, $this->handler->handle($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testPrintTreeWithRelativePath()
    {
        $args = self::$treeCommand->parseArgs(new StringArgs('app'));

        $this->repo->add('/app', new TestDirectory('/app', array(
            new TestDirectory('/app/dir1', array(
                new TestFile('/app/dir1/file1'),
            )),
            new TestDirectory('/app/dir2', array(
                new TestFile('/app/dir2/file1'),
                new TestFile('/app/dir2/file2'),
            )),
            new TestFile('/app/file'),
            new TestFile('/app/resource1'),
            new TestFile('/app/resource2'),
        )));

        $expected = <<<EOF
/app
├── dir1
│   └── file1
├── dir2
│   ├── file1
│   └── file2
├── file
├── resource1
└── resource2

8 resources

EOF;

        $this->assertSame(0, $this->handler->handle($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testPrintTreeWithAbsolutePath()
    {
        $args = self::$treeCommand->parseArgs(new StringArgs('/app'));

        $this->repo->add('/app', new TestDirectory('/app', array(
            new TestDirectory('/app/dir1', array(
                new TestFile('/app/dir1/file1'),
            )),
            new TestDirectory('/app/dir2', array(
                new TestFile('/app/dir2/file1'),
                new TestFile('/app/dir2/file2'),
            )),
            new TestFile('/app/file'),
            new TestFile('/app/resource1'),
            new TestFile('/app/resource2'),
        )));

        $expected = <<<EOF
/app
├── dir1
│   └── file1
├── dir2
│   ├── file1
│   └── file2
├── file
├── resource1
└── resource2

8 resources

EOF;

        $this->assertSame(0, $this->handler->handle($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }
}
