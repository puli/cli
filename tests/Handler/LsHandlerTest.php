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

use DateTime;
use Puli\Cli\Handler\LsHandler;
use Puli\Repository\InMemoryRepository;
use Puli\Repository\Tests\Resource\TestDirectory;
use Puli\Repository\Tests\Resource\TestFile;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LsHandlerTest extends AbstractHandlerTest
{
    /**
     * @var Command
     */
    private static $lsCommand;

    /**
     * @var InMemoryRepository
     */
    private $repo;

    /**
     * @var LsHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$lsCommand = self::$application->getCommand('ls');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->repo = new InMemoryRepository();
        $this->handler = new LsHandler($this->repo);
    }

    public function testListRelativePath()
    {
        $args = self::$lsCommand->parseArgs(new StringArgs('app'));

        $this->repo->add('/app', new TestDirectory('/app', array(
            new TestFile('/app/file'),
            new TestFile('/app/resource1'),
            new TestFile('/app/resource2'),
        )));

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
file  resource1  resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListAbsolutePath()
    {
        $args = self::$lsCommand->parseArgs(new StringArgs('/app'));

        $this->repo->add('/app', new TestDirectory('/app', array(
            new TestFile('/app/file'),
            new TestFile('/app/resource1'),
            new TestFile('/app/resource2'),
        )));

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
file  resource1  resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNoPath()
    {
        $args = self::$lsCommand->parseArgs(new StringArgs(''));

        $this->repo->add('/', new TestDirectory('/', array(
            new TestFile('/file'),
            new TestFile('/resource1'),
            new TestFile('/resource2'),
        )));

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
file  resource1  resource2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testListResourceWithoutChildren()
    {
        $args = self::$lsCommand->parseArgs(new StringArgs('/app'));

        $this->repo->add('/app', new TestDirectory('/app'));

        $this->handler->handle($args, $this->io);
    }

    public function testListLong()
    {
        $args = self::$lsCommand->parseArgs(new StringArgs('-l /app'));

        $this->repo->add('/app', new TestDirectory('/app', array(
            $dir1 = new TestDirectory('/app/dir1'),
            $file1 = new TestFile('/app/file1'),
            $file2 = new TestFile('/app/file2'),
        )));

        $dir1->getMetadata()->setModificationTime(1234);
        $dir1->getMetadata()->setSize(12);
        $file1->getMetadata()->setModificationTime(2345);
        $file1->getMetadata()->setSize(34);
        $file2->getMetadata()->setModificationTime(3456);
        $file2->getMetadata()->setSize(56);

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
TestDirectory 12 Jan 1 1970 dir1
TestFile      34 Jan 1 1970 file1
TestFile      56 Jan 1 1970 file2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    /**
     * @dataProvider getSizes
     */
    public function testListLongFormatsSizeToThreeDigits($size, $formattedSize)
    {
        $args = self::$lsCommand->parseArgs(new StringArgs('-l /app'));

        $this->repo->add('/app', new TestDirectory('/app', array(
            $dir1 = new TestDirectory('/app/dir1'),
        )));

        $dir1->getMetadata()->setModificationTime(1234);
        $dir1->getMetadata()->setSize($size);

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
TestDirectory $formattedSize Jan 1 1970 dir1

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function getSizes()
    {
        // Same behavior as UNIX ls
        return array(
            array(12, '12'),
            array(1023, '1023'),
            array(1024, '1.0K'),
            array(9.9*1024, '9.9K'),
            array(10.1*1024, '10K'),
            array(10.9*1024, '11K'),
            array(1023*1024, '1023K'),
            array(1023*1024+1, '1.0M'),
            array(1024*1024-1, '1.0M'),
            array(1024*1024, '1.0M'),
            array(1023*1024*1024, '1023M'),
            array(1023*1024*1024+1, '1.0G'),
            array(1024*1024*1024-1, '1.0G'),
            array(1024*1024*1024, '1.0G'),
            array(1023*1024*1024*1024, '1023G'),
            array(1023*1024*1024*1024+1, '1.0T'),
            array(1024*1024*1024*1024-1, '1.0T'),
            array(1024*1024*1024*1024, '1.0T'),
            array(1023*1024*1024*1024*1024, '1023T'),
            array(1023*1024*1024*1024*1024+1, '1.0P'),
            array(1024*1024*1024*1024*1024-1, '1.0P'),
            array(1024*1024*1024*1024*1024, '1.0P'),
        );
    }

    /**
     * @dataProvider getYears
     */
    public function testListLongFormatsYearDependingOnCurrentYear(DateTime $timestamp, $formattedYear)
    {
        $args = self::$lsCommand->parseArgs(new StringArgs('-l /app'));

        $this->repo->add('/app', new TestDirectory('/app', array(
            $dir1 = new TestDirectory('/app/dir1'),
        )));

        $dir1->getMetadata()->setModificationTime((int) $timestamp->format('U'));
        $dir1->getMetadata()->setSize(12);

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
TestDirectory 12 Feb 3 $formattedYear dir1

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function getYears()
    {
        $today = new DateTime();
        $currentYear = (int) $today->format('Y');
        $lastYear = $currentYear - 1;

        return array(
            array(new DateTime("$currentYear-02-03 00:00"), '00:00'),
            array(new DateTime("$currentYear-02-03 12:34"), '12:34'),
            array(new DateTime("$currentYear-02-03 23:55"), '23:55'),
            array(new DateTime("$lastYear-02-03 12:34"), $lastYear),
        );
    }
}
