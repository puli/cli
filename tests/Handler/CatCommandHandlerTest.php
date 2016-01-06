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

use Puli\Cli\Handler\CatCommandHandler;
use Puli\Cli\Tests\Fixtures\TestDirectory;
use Puli\Cli\Tests\Fixtures\TestFile;
use Puli\Repository\InMemoryRepository;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;

/**
 * @since  1.0
 *
 * @author Stephan Wentz <swentz@brainbits.net>
 */
class CatCommandHandlerTest extends AbstractCommandHandlerTest
{
    /**
     * @var Command
     */
    private static $catCommand;

    /**
     * @var InMemoryRepository
     */
    private $repo;

    /**
     * @var CatCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$catCommand = self::$application->getCommand('cat');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->repo = new InMemoryRepository();
        $this->handler = new CatCommandHandler($this->repo);
    }

    public function testListRelativePath()
    {
        $args = self::$catCommand->parseArgs(new StringArgs('app/*'));

        $this->repo->add('/app', new TestDirectory('/app', array(
            new TestFile('/app/file', 'testA'),
            new TestFile('/app/resource1', 'testB'),
            new TestFile('/app/resource2', 'testC'),
        )));

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
testA
testB
testC

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListAbsolutePath()
    {
        $args = self::$catCommand->parseArgs(new StringArgs('/app/*'));

        $this->repo->add('/app', new TestDirectory('/app', array(
            new TestFile('/app/file', 'testA'),
            new TestFile('/app/resource1', 'testB'),
            new TestFile('/app/resource2', 'testC'),
        )));

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
testA
testB
testC

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListAbsoluteFilePath()
    {
        $args = self::$catCommand->parseArgs(new StringArgs('/app/file'));

        $this->repo->add('/app', new TestDirectory('/app', array(
            new TestFile('/app/file', 'testA'),
            new TestFile('/app/resource1', 'testB'),
            new TestFile('/app/resource2', 'testC'),
        )));

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
testA

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }
}
