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
use Puli\Cli\Handler\UrlCommandHandler;
use Puli\Repository\Api\ResourceRepository;
use Puli\Repository\Resource\Collection\ArrayResourceCollection;
use Puli\Repository\Resource\GenericResource;
use Puli\UrlGenerator\Api\UrlGenerator;
use Webmozart\Console\Args\StringArgs;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UrlCommandHandlerTest extends AbstractCommandHandlerTest
{
    /**
     * @var Command
     */
    private static $urlCommand;

    /**
     * @var UrlGenerator|PHPUnit_Framework_MockObject_MockObject
     */
    private $urlGenerator;

    /**
     * @var ResourceRepository|PHPUnit_Framework_MockObject_MockObject
     */
    private $repo;

    /**
     * @var UrlCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$urlCommand = self::$application->getCommand('url');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->urlGenerator = $this->getMock('Puli\UrlGenerator\Api\UrlGenerator');
        $this->repo = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $this->handler = new UrlCommandHandler($this->urlGenerator, $this->repo);
    }

    public function testGenerateRelativePath()
    {
        $args = self::$urlCommand->parseArgs(new StringArgs('app/public/logo.png'));

        $this->urlGenerator->expects($this->once())
            ->method('generateUrl')
            ->with('/app/public/logo.png')
            ->willReturn('http://example.com/logo.png');

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
http://example.com/logo.png

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testGenerateAbsolutePath()
    {
        $args = self::$urlCommand->parseArgs(new StringArgs('/app/public/logo.png'));

        $this->urlGenerator->expects($this->once())
            ->method('generateUrl')
            ->with('/app/public/logo.png')
            ->willReturn('http://example.com/logo.png');

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
http://example.com/logo.png

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testGenerateMultiplePaths()
    {
        $args = self::$urlCommand->parseArgs(new StringArgs('/app/public/logo.png /app/public/style.css'));

        $this->urlGenerator->expects($this->at(0))
            ->method('generateUrl')
            ->with('/app/public/logo.png')
            ->willReturn('http://example.com/logo.png');
        $this->urlGenerator->expects($this->at(1))
            ->method('generateUrl')
            ->with('/app/public/style.css')
            ->willReturn('http://example.com/style.css');

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
http://example.com/logo.png
http://example.com/style.css

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testGenerateGlob()
    {
        $args = self::$urlCommand->parseArgs(new StringArgs('/app/public/*'));

        $this->repo->expects($this->once())
            ->method('find')
            ->with('/app/public/*')
            ->willReturn(new ArrayResourceCollection(array(
                new GenericResource('/app/public/logo.png'),
                new GenericResource('/app/public/style.css'),
            )));

        $this->urlGenerator->expects($this->at(0))
            ->method('generateUrl')
            ->with('/app/public/logo.png')
            ->willReturn('http://example.com/logo.png');
        $this->urlGenerator->expects($this->at(1))
            ->method('generateUrl')
            ->with('/app/public/style.css')
            ->willReturn('http://example.com/style.css');

        $statusCode = $this->handler->handle($args, $this->io);

        $expected = <<<EOF
http://example.com/logo.png
http://example.com/style.css

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }
}
