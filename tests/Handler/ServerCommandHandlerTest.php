<?php

/*
 * This file is part of the puli/asset-plugin package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Tests\Handler;

use PHPUnit_Framework_MockObject_MockObject;
use Puli\Cli\Handler\ServerCommandHandler;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\Manager\Api\Server\ServerManager;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ServerCommandHandlerTest extends AbstractCommandHandlerTest
{
    /**
     * @var Command
     */
    private static $listCommand;

    /**
     * @var Command
     */
    private static $addCommand;

    /**
     * @var Command
     */
    private static $updateCommand;

    /**
     * @var Command
     */
    private static $deleteCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ServerManager
     */
    private $serverManager;

    /**
     * @var ServerCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$listCommand = self::$application->getCommand('server')->getSubCommand('list');
        self::$addCommand = self::$application->getCommand('server')->getSubCommand('add');
        self::$updateCommand = self::$application->getCommand('server')->getSubCommand('update');
        self::$deleteCommand = self::$application->getCommand('server')->getSubCommand('delete');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->serverManager = $this->getMock('Puli\Manager\Api\Server\ServerManager');
        $this->handler = new ServerCommandHandler($this->serverManager);
    }

    public function testListServers()
    {
        $servers = new ServerCollection(array(
            new Server('localhost', 'symlink', 'public_html', '/%s'),
            new Server('example.com', 'rsync', 'ssh://example.com', 'http://example.com/%s', array(
                'user' => 'webmozart',
                'password' => 'password',
            )),
        ));

        $this->serverManager->expects($this->any())
            ->method('getServers')
            ->willReturn($servers);

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $expected = <<<EOF
Server Name  Installer  Location           URL Format
localhost    symlink    public_html        /%s
example.com  rsync      ssh://example.com  http://example.com/%s

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEmpty()
    {
        $servers = new ServerCollection(array());

        $this->serverManager->expects($this->any())
            ->method('getServers')
            ->willReturn($servers);

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $expected = <<<EOF
No servers. Use "puli server --add <name> <document-root>" to add a server.

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testAddServer()
    {
        $server = new Server('localhost', 'symlink', 'public_html');

        $this->serverManager->expects($this->once())
            ->method('addServer')
            ->with($server);

        $args = self::$addCommand->parseArgs(new StringArgs('localhost public_html'));

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddServerWithInstaller()
    {
        $server = new Server('localhost', 'copy', 'public_html');

        $this->serverManager->expects($this->once())
            ->method('addServer')
            ->with($server);

        $args = self::$addCommand->parseArgs(new StringArgs('localhost public_html --installer copy'));

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddServerWithUrlFormat()
    {
        $server = new Server('localhost', 'symlink', 'public_html', '/blog/%s');

        $this->serverManager->expects($this->once())
            ->method('addServer')
            ->with($server);

        $args = self::$addCommand->parseArgs(new StringArgs('localhost public_html --url-format /blog/%s'));

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddServerWithParameters()
    {
        $server = new Server('localhost', 'symlink', 'public_html', Server::DEFAULT_URL_FORMAT, array(
            'param1' => 'value1',
            'param2' => 'value2',
        ));

        $this->serverManager->expects($this->once())
            ->method('addServer')
            ->with($server);

        $args = self::$addCommand->parseArgs(new StringArgs('localhost public_html --param param1=value1 --param param2=value2'));

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testFailIfInvalidParameter()
    {
        $this->serverManager->expects($this->never())
            ->method('addServer');

        $args = self::$addCommand->parseArgs(new StringArgs('localhost public_html --param param1'));

        $this->handler->handleAdd($args);
    }

    public function testUpdateServer()
    {
        $server = new Server('localhost', 'symlink', 'public_html', '/%s', array(
            'param1' => 'old',
            'param2' => 'value2',
        ));

        $this->serverManager->expects($this->once())
            ->method('hasServer')
            ->with('localhost')
            ->willReturn(true);

        $this->serverManager->expects($this->once())
            ->method('getServer')
            ->with('localhost')
            ->willReturn($server);

        $this->serverManager->expects($this->once())
            ->method('addServer')
            ->with(new Server('localhost', 'copy', 'web', '/dir/%s', array(
                'param1' => 'new',
                'param2' => 'value2',
            )));

        $args = self::$updateCommand->parseArgs(new StringArgs('localhost --installer copy --document-root web --url-format /dir/%s --param param1=new'));

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateServerWithRemovedParameters()
    {
        $server = new Server('localhost', 'symlink', 'public_html', '/%s', array(
            'param1' => 'value1',
            'param2' => 'value2',
        ));

        $this->serverManager->expects($this->once())
            ->method('hasServer')
            ->with('localhost')
            ->willReturn(true);

        $this->serverManager->expects($this->once())
            ->method('getServer')
            ->with('localhost')
            ->willReturn($server);

        $this->serverManager->expects($this->once())
            ->method('addServer')
            ->with(new Server('localhost', 'symlink', 'public_html', '/%s', array(
                'param2' => 'value2',
            )));

        $args = self::$updateCommand->parseArgs(new StringArgs('localhost --unset-param param1'));

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testUpdateServerFailsIfNoChange()
    {
        $server = new Server('localhost', 'symlink', 'public_html');

        $this->serverManager->expects($this->once())
            ->method('hasServer')
            ->with('localhost')
            ->willReturn(true);

        $this->serverManager->expects($this->once())
            ->method('getServer')
            ->with('localhost')
            ->willReturn($server);

        $this->serverManager->expects($this->never())
            ->method('addServer');

        $args = self::$updateCommand->parseArgs(new StringArgs('localhost'));

        $this->handler->handleUpdate($args);
    }

    public function testDeleteServer()
    {
        $this->serverManager->expects($this->once())
            ->method('hasServer')
            ->with('localhost')
            ->willReturn(true);

        $this->serverManager->expects($this->once())
            ->method('removeServer')
            ->with('localhost');

        $args = self::$deleteCommand->parseArgs(new StringArgs('localhost'));

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    /**
     * @expectedException \Puli\Manager\Api\Server\NoSuchServerException
     */
    public function testDeleteServerFailsIfNotFound()
    {
        $this->serverManager->expects($this->once())
            ->method('hasServer')
            ->with('localhost')
            ->willReturn(false);

        $this->serverManager->expects($this->never())
            ->method('removeServer');

        $args = self::$deleteCommand->parseArgs(new StringArgs('localhost'));

        $this->handler->handleDelete($args);
    }
}
