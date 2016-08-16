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
use Puli\Cli\Handler\UpgradeCommandHandler;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\Module\RootModuleFileManager;
use Puli\Manager\Module\ModuleFileConverter;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UpgradeCommandHandlerTest extends AbstractCommandHandlerTest
{
    /**
     * @var Command
     */
    private static $upgradeCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|RootModuleFileManager
     */
    private $moduleFileManager;

    /**
     * @var RootModuleFile
     */
    private $moduleFile;

    /**
     * @var UpgradeCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$upgradeCommand = self::$application->getCommand('upgrade');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->moduleFileManager = $this->getMock('Puli\Manager\Api\Module\RootModuleFileManager');
        $this->moduleFile = new RootModuleFile();
        $this->moduleFileManager->expects($this->any())
            ->method('getModuleFile')
            ->willReturn($this->moduleFile);
        $this->handler = new UpgradeCommandHandler($this->moduleFileManager);
    }

    public function testUpgradeToDefaultVersion()
    {
        $args = self::$upgradeCommand->parseArgs(new StringArgs(''));
        $defaultVersion = ModuleFileConverter::VERSION;

        $this->moduleFile->setVersion('0.5');

        $this->moduleFileManager->expects($this->once())
            ->method('migrate')
            ->with($defaultVersion);

        $expected = <<<EOF
Migrated your puli.json from version 0.5 to version $defaultVersion.

EOF;

        $this->assertSame(0, $this->handler->handle($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testUpgradeToExplicitVersion()
    {
        $args = self::$upgradeCommand->parseArgs(new StringArgs('0.8'));

        $this->moduleFile->setVersion('0.5');

        $this->moduleFileManager->expects($this->once())
            ->method('migrate')
            ->with('0.8');

        $expected = <<<'EOF'
Migrated your puli.json from version 0.5 to version 0.8.

EOF;

        $this->assertSame(0, $this->handler->handle($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testUpgradeDoesNothingIfAlreadyCorrectVersion()
    {
        $args = self::$upgradeCommand->parseArgs(new StringArgs('0.5'));

        $this->moduleFile->setVersion('0.5');

        $this->moduleFileManager->expects($this->never())
            ->method('migrate');

        $expected = <<<'EOF'
Your puli.json is already at version 0.5.

EOF;

        $this->assertSame(0, $this->handler->handle($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }
}
