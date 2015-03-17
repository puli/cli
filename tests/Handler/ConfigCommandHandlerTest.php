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
use Puli\Cli\Handler\ConfigCommandHandler;
use Puli\Manager\Api\Package\RootPackageFileManager;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigCommandHandlerTest extends AbstractCommandHandlerTest
{
    /**
     * @var Command
     */
    private static $listCommand;

    /**
     * @var Command
     */
    private static $showCommand;

    /**
     * @var Command
     */
    private static $setCommand;

    /**
     * @var Command
     */
    private static $deleteCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|RootPackageFileManager
     */
    private $manager;

    /**
     * @var ConfigCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$listCommand = self::$application->getCommand('config')->getSubCommand('list');
        self::$showCommand = self::$application->getCommand('config')->getSubCommand('show');
        self::$setCommand = self::$application->getCommand('config')->getSubCommand('set');
        self::$deleteCommand = self::$application->getCommand('config')->getSubCommand('delete');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->manager = $this->getMock('Puli\Manager\Api\Package\RootPackageFileManager');
        $this->handler = new ConfigCommandHandler($this->manager);
    }

    public function getValues()
    {
        return array(
            array('value', 'value'),
            array(1, '1'),
            array(null, 'null'),
            array(false, 'false'),
            array(true, 'true'),
        );
    }

    /**
     * @dataProvider getValues
     */
    public function testListKeys($nativeValue, $stringValue)
    {
        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $this->manager->expects($this->once())
            ->method('getConfigKeys')
            ->with(false, false)
            ->willReturn(array(
                'key' => $nativeValue,
                'longer-key' => 'longer value',
            ));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
key = $stringValue
longer-key = longer value

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListAllKeys()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--all'));

        $this->manager->expects($this->once())
            ->method('getConfigKeys')
            ->with(true, true)
            ->willReturn(array(
                'key1' => 'value1',
                'key2' => 'value2',
            ));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
key1 = value1
key2 = value2

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    /**
     * @dataProvider getValues
     */
    public function testShowKey($nativeValue, $stringValue)
    {
        $args = self::$showCommand->parseArgs(new StringArgs('the-key'));

        $this->manager->expects($this->once())
            ->method('getConfigKey')
            ->with('the-key', null, true)
            ->willReturn($nativeValue);

        $statusCode = $this->handler->handleShow($args, $this->io);

        $expected = <<<EOF
$stringValue

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    /**
     * @dataProvider getValues
     */
    public function testSetKey($nativeValue, $stringValue)
    {
        $args = self::$setCommand->parseArgs(new StringArgs('the-key '.$stringValue));

        $this->manager->expects($this->once())
            ->method('setConfigKey')
            ->with('the-key', $nativeValue);

        $statusCode = $this->handler->handleSet($args);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testDeleteKey()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('the-key'));

        $this->manager->expects($this->once())
            ->method('removeConfigKey')
            ->with('the-key');

        $statusCode = $this->handler->handleDelete($args);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }
}
