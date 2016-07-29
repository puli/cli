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
use Puli\Manager\Api\Module\RootModuleFileManager;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;

/**
 * @since  1.0
 *
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
    private static $resetCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|RootModuleFileManager
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
        self::$resetCommand = self::$application->getCommand('config')->getSubCommand('reset');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->manager = $this->getMock('Puli\Manager\Api\Module\RootModuleFileManager');
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

        $this->manager->expects($this->at(0))
            ->method('getConfigKeys')
            ->with(false, false, true)
            ->willReturn(array(
                'longer-key' => true,
            ));

        $this->manager->expects($this->at(1))
            ->method('getConfigKeys')
            ->with(true, true, true)
            ->willReturn(array(
                'key' => $nativeValue,
                'longer-key' => true,
            ));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Config Key  User Value  Effective Value
key                     $stringValue
longer-key  true        true

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListParsed()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--parsed'));

        $this->manager->expects($this->at(0))
            ->method('getConfigKeys')
            ->with(false, false, false)
            ->willReturn(array(
                'key' => 'value',
            ));

        $this->manager->expects($this->at(1))
            ->method('getConfigKeys')
            ->with(true, true, false)
            ->willReturn(array(
                'key' => 'value',
            ));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
Config Key  User Value  Effective Value
key         value       value

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
            ->with('the-key', null, true, true)
            ->willReturn($nativeValue);

        $statusCode = $this->handler->handleShow($args, $this->io);

        $expected = <<<EOF
$stringValue

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testShowParsed()
    {
        $args = self::$showCommand->parseArgs(new StringArgs('the-key --parsed'));

        $this->manager->expects($this->once())
            ->method('getConfigKey')
            ->with('the-key', null, true, false)
            ->willReturn('value');

        $statusCode = $this->handler->handleShow($args, $this->io);

        $expected = <<<'EOF'
value

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

    public function testResetKey()
    {
        $args = self::$resetCommand->parseArgs(new StringArgs('the-key'));

        $this->manager->expects($this->once())
            ->method('removeConfigKey')
            ->with('the-key');

        $statusCode = $this->handler->handleReset($args);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }
}
