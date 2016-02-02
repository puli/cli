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
use Puli\Cli\Handler\PluginCommandHandler;
use Puli\Manager\Api\Package\RootPackageFileManager;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PluginCommandHandlerTest extends AbstractCommandHandlerTest
{
    /**
     * @var Command
     */
    private static $listCommand;

    /**
     * @var Command
     */
    private static $installCommand;

    /**
     * @var Command
     */
    private static $deleteCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|RootPackageFileManager
     */
    private $manager;

    /**
     * @var PluginCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$listCommand = self::$application->getCommand('plugin')->getSubCommand('list');
        self::$installCommand = self::$application->getCommand('plugin')->getSubCommand('install');
        self::$deleteCommand = self::$application->getCommand('plugin')->getSubCommand('delete');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->manager = $this->getMock('Puli\Manager\Api\Package\RootPackageFileManager');
        $this->handler = new PluginCommandHandler($this->manager);
    }

    public function testListPlugins()
    {
        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $this->manager->expects($this->once())
            ->method('getPluginClasses')
            ->willReturn(array(
                'My\Plugin\Class',
                'Other\Plugin\Class',
            ));

        $expected = <<<EOF
My\Plugin\Class
Other\Plugin\Class

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNoPlugins()
    {
        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $this->manager->expects($this->once())
            ->method('getPluginClasses')
            ->willReturn(array());

        $expected = <<<'EOF'
No plugin classes. Use "puli plugin --install <class>" to install a plugin class.

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testInstallPlugin()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('My\Plugin\Class'));

        $this->manager->expects($this->once())
            ->method('hasPluginClass')
            ->with('My\Plugin\Class')
            ->willReturn(false);

        $this->manager->expects($this->once())
            ->method('addPluginClass')
            ->with('My\Plugin\Class');

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testInstallPluginFailsIfAlreadyInstalled()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('My\Plugin\Class'));

        $this->manager->expects($this->once())
            ->method('hasPluginClass')
            ->with('My\Plugin\Class')
            ->willReturn(true);

        $this->manager->expects($this->never())
            ->method('addPluginClass');

        $this->handler->handleInstall($args);
    }

    public function testDeletePlugin()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('My\Plugin\Class'));

        $this->manager->expects($this->once())
            ->method('hasPluginClass')
            ->with('My\Plugin\Class')
            ->willReturn(true);

        $this->manager->expects($this->once())
            ->method('removePluginClass')
            ->with('My\Plugin\Class');

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testDeletePluginFailsIfNotInstalled()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('My\Plugin\Class'));

        $this->manager->expects($this->once())
            ->method('hasPluginClass')
            ->with('My\Plugin\Class')
            ->willReturn(false);

        $this->manager->expects($this->never())
            ->method('removePluginClass');

        $this->handler->handleDelete($args);
    }
}
