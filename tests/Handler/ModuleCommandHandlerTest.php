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
use Puli\Cli\Handler\ModuleCommandHandler;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\ModuleManager;
use Puli\Manager\Api\Module\ModuleState;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use RuntimeException;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleCommandHandlerTest extends AbstractCommandHandlerTest
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
    private static $renameCommand;

    /**
     * @var Command
     */
    private static $deleteCommand;

    /**
     * @var Command
     */
    private static $cleanCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ProjectContext
     */
    private $context;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ModuleManager
     */
    private $moduleManager;

    /**
     * @var ModuleCommandHandler
     */
    private $handler;

    /**
     * @var string
     */
    private $wd;

    /**
     * @var string
     */
    private $previousWd;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$listCommand = self::$application->getCommand('module')->getSubCommand('list');
        self::$installCommand = self::$application->getCommand('module')->getSubCommand('install');
        self::$renameCommand = self::$application->getCommand('module')->getSubCommand('rename');
        self::$deleteCommand = self::$application->getCommand('module')->getSubCommand('delete');
        self::$cleanCommand = self::$application->getCommand('module')->getSubCommand('clean');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->context = $this->getMockBuilder('Puli\Manager\Api\Context\ProjectContext')
            ->disableOriginalConstructor()
            ->getMock();
        $this->moduleManager = $this->getMock('Puli\Manager\Api\Module\ModuleManager');
        $this->handler = new ModuleCommandHandler($this->moduleManager);

        $this->context->expects($this->any())
            ->method('getRootDirectory')
            ->willReturn(__DIR__.'/Fixtures/root');

        $this->moduleManager->expects($this->any())
            ->method('getContext')
            ->willReturn($this->context);

        $installInfo1 = new InstallInfo('vendor/module1', 'modules/module1');
        $installInfo2 = new InstallInfo('vendor/module2', 'modules/module2');
        $installInfo3 = new InstallInfo('vendor/module3', 'modules/module3');
        $installInfo4 = new InstallInfo('vendor/module4', 'modules/module4');
        $installInfo5 = new InstallInfo('vendor/module5', 'modules/module5');

        $installInfo1->setInstallerName('spock');
        $installInfo2->setInstallerName('spock');
        $installInfo3->setInstallerName('kirk');
        $installInfo4->setInstallerName('spock');
        $installInfo5->setInstallerName('spock');
        $installInfo5->setEnvironment(Environment::DEV);

        $rootModule = new RootModule(new RootModuleFile('vendor/root'), __DIR__.'/Fixtures/root');
        $module1 = new Module(new ModuleFile('vendor/module1'), __DIR__.'/Fixtures/root/modules/module1', $installInfo1);
        $module2 = new Module(new ModuleFile('vendor/module2'), __DIR__.'/Fixtures/root/modules/module2', $installInfo2);
        $module3 = new Module(new ModuleFile('vendor/module3'), __DIR__.'/Fixtures/root/modules/module3', $installInfo3);
        $module4 = new Module(null, __DIR__.'/Fixtures/root/modules/module4', $installInfo4, array(new RuntimeException('Load error')));
        $module5 = new Module(new ModuleFile('vendor/module5'), __DIR__.'/Fixtures/root/modules/module5', $installInfo5);

        $this->moduleManager->expects($this->any())
            ->method('findModules')
            ->willReturnCallback($this->returnFromMap(array(
                array($this->all(), new ModuleList(array($rootModule, $module1, $module2, $module3, $module4, $module5))),
                array($this->env(array(Environment::PROD, Environment::DEV)), new ModuleList(array($rootModule, $module1, $module2, $module3, $module4, $module5))),
                array($this->env(array(Environment::PROD)), new ModuleList(array($rootModule, $module1, $module2, $module3, $module4))),
                array($this->env(array(Environment::DEV)), new ModuleList(array($module5))),
                array($this->installer('spock'), new ModuleList(array($module1, $module2, $module4, $module5))),
                array($this->state(ModuleState::ENABLED), new ModuleList(array($rootModule, $module1, $module2, $module5))),
                array($this->state(ModuleState::NOT_FOUND), new ModuleList(array($module3))),
                array($this->state(ModuleState::NOT_LOADABLE), new ModuleList(array($module4))),
                array($this->states(array(ModuleState::ENABLED, ModuleState::NOT_FOUND)), new ModuleList(array($rootModule, $module1, $module2, $module3, $module5))),
                array($this->installerAndState('spock', ModuleState::ENABLED), new ModuleList(array($module1, $module2, $module5))),
                array($this->installerAndState('spock', ModuleState::NOT_FOUND), new ModuleList(array())),
                array($this->installerAndState('spock', ModuleState::NOT_LOADABLE), new ModuleList(array($module4))),
            )));

        $this->previousWd = getcwd();
        $this->wd = Path::normalize(__DIR__);

        chdir($this->wd);
    }

    protected function tearDown()
    {
        chdir($this->previousWd);
    }

    public function testListModules()
    {
        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
The following modules are currently enabled:

    Module Name     Installer  Env   Install Path
    vendor/module1  spock      prod  modules/module1
    vendor/module2  spock      prod  modules/module2
    vendor/module5  spock      dev   modules/module5
    vendor/root                prod  .

The following modules could not be found:
 (use "puli module --clean" to remove)

    Module Name     Installer  Env   Install Path
    vendor/module3  kirk       prod  modules/module3

The following modules could not be loaded:

    Module Name     Error
    vendor/module4  RuntimeException: Load error


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListModulesByInstaller()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--installer spock'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
The following modules are currently enabled:

    Module Name     Installer  Env   Install Path
    vendor/module1  spock      prod  modules/module1
    vendor/module2  spock      prod  modules/module2
    vendor/module5  spock      dev   modules/module5

The following modules could not be loaded:

    Module Name     Error
    vendor/module4  RuntimeException: Load error


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledModules()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
Module Name     Installer  Env   Install Path
vendor/module1  spock      prod  modules/module1
vendor/module2  spock      prod  modules/module2
vendor/module5  spock      dev   modules/module5
vendor/root                prod  .

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNotFoundModules()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--not-found'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
Module Name     Installer  Env   Install Path
vendor/module3  kirk       prod  modules/module3

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNotLoadableModules()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--not-loadable'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
Module Name     Error
vendor/module4  RuntimeException: Load error

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledAndNotFoundModules()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --not-found'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
The following modules are currently enabled:

    Module Name     Installer  Env   Install Path
    vendor/module1  spock      prod  modules/module1
    vendor/module2  spock      prod  modules/module2
    vendor/module5  spock      dev   modules/module5
    vendor/root                prod  .

The following modules could not be found:
 (use "puli module --clean" to remove)

    Module Name     Installer  Env   Install Path
    vendor/module3  kirk       prod  modules/module3


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledModulesByInstaller()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --installer spock'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
Module Name     Installer  Env   Install Path
vendor/module1  spock      prod  modules/module1
vendor/module2  spock      prod  modules/module2
vendor/module5  spock      dev   modules/module5

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListDevModules()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--dev'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
The following modules are currently enabled:

    Module Name     Installer  Env  Install Path
    vendor/module5  spock      dev  modules/module5


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNoDevModules()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--prod'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
The following modules are currently enabled:

    Module Name     Installer  Env   Install Path
    vendor/module1  spock      prod  modules/module1
    vendor/module2  spock      prod  modules/module2
    vendor/root                prod  .

The following modules could not be found:
 (use "puli module --clean" to remove)

    Module Name     Installer  Env   Install Path
    vendor/module3  kirk       prod  modules/module3

The following modules could not be loaded:

    Module Name     Error
    vendor/module4  RuntimeException: Load error


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListDevAndNoDevModules()
    {
        // Same as if passing none of the too
        // Does not make much sense but could occur if building the
        // "module list" command dynamically
        $args = self::$listCommand->parseArgs(new StringArgs('--dev --prod'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
The following modules are currently enabled:

    Module Name     Installer  Env   Install Path
    vendor/module1  spock      prod  modules/module1
    vendor/module2  spock      prod  modules/module2
    vendor/module5  spock      dev   modules/module5
    vendor/root                prod  .

The following modules could not be found:
 (use "puli module --clean" to remove)

    Module Name     Installer  Env   Install Path
    vendor/module3  kirk       prod  modules/module3

The following modules could not be loaded:

    Module Name     Error
    vendor/module4  RuntimeException: Load error


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListModulesWithFormat()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--format %name%:%installer%:%install_path%:%state%:%env%'));

        $rootDir = $this->context->getRootDirectory();
        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root::$rootDir:enabled:prod
vendor/module1:spock:$rootDir/modules/module1:enabled:prod
vendor/module2:spock:$rootDir/modules/module2:enabled:prod
vendor/module3:kirk:$rootDir/modules/module3:not-found:prod
vendor/module4:spock:$rootDir/modules/module4:not-loadable:prod
vendor/module5:spock:$rootDir/modules/module5:enabled:dev

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testInstallModuleWithRelativePath()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('modules/module1'));

        $this->moduleManager->expects($this->once())
            ->method('installModule')
            ->with($this->wd.'/modules/module1', null, InstallInfo::DEFAULT_INSTALLER_NAME, Environment::PROD);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testInstallModuleWithAbsolutePath()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('/modules/module1'));

        $this->moduleManager->expects($this->once())
            ->method('installModule')
            ->with('/modules/module1', null, InstallInfo::DEFAULT_INSTALLER_NAME, Environment::PROD);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testInstallModuleWithCustomName()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('/modules/module1 custom/module1'));

        $this->moduleManager->expects($this->once())
            ->method('installModule')
            ->with('/modules/module1', 'custom/module1', InstallInfo::DEFAULT_INSTALLER_NAME, Environment::PROD);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testInstallModuleWithCustomInstaller()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('--installer kirk /modules/module1'));

        $this->moduleManager->expects($this->once())
            ->method('installModule')
            ->with('/modules/module1', null, 'kirk', Environment::PROD);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testInstallDevModule()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('--dev modules/module1'));

        $this->moduleManager->expects($this->once())
            ->method('installModule')
            ->with($this->wd.'/modules/module1', null, InstallInfo::DEFAULT_INSTALLER_NAME, Environment::DEV);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testRenameModule()
    {
        $args = self::$renameCommand->parseArgs(new StringArgs('vendor/module1 vendor/new'));

        $this->moduleManager->expects($this->once())
            ->method('renameModule')
            ->with('vendor/module1', 'vendor/new');

        $this->assertSame(0, $this->handler->handleRename($args));
    }

    public function testDeleteModule()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('vendor/module1'));

        $this->moduleManager->expects($this->once())
            ->method('hasModule')
            ->with('vendor/module1')
            ->willReturn(true);

        $this->moduleManager->expects($this->once())
            ->method('removeModule')
            ->with('vendor/module1');

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The module "vendor/module1" is not installed.
     */
    public function testDeleteModuleFailsIfNotFound()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('vendor/module1'));

        $this->moduleManager->expects($this->once())
            ->method('hasModule')
            ->with('vendor/module1')
            ->willReturn(false);

        $this->moduleManager->expects($this->never())
            ->method('removeModule')
            ->with('vendor/module1');

        $this->handler->handleDelete($args);
    }

    public function testCleanModules()
    {
        $args = self::$cleanCommand->parseArgs(new StringArgs(''));

        // The not-found module
        $this->moduleManager->expects($this->once())
            ->method('removeModule')
            ->with('vendor/module3');

        $expected = <<<'EOF'
Removing vendor/module3

EOF;

        $this->assertSame(0, $this->handler->handleClean($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    private function all()
    {
        return Expr::true();
    }

    private function env(array $envs)
    {
        return Expr::method('getInstallInfo', Expr::method('getEnvironment', Expr::in($envs)));
    }

    private function state($state)
    {
        return Expr::method('getState', Expr::same($state));
    }

    private function states(array $states)
    {
        return Expr::method('getState', Expr::in($states));
    }

    private function installer($installer)
    {
        return Expr::method('getInstallInfo', Expr::method('getInstallerName', Expr::same($installer)));
    }

    private function installerAndState($installer, $state)
    {
        return Expr::method('getInstallInfo', Expr::method('getInstallerName', Expr::same($installer)))
            ->andMethod('getState', Expr::same($state));
    }

    private function returnFromMap(array $map)
    {
        return function (Expression $expr) use ($map) {
            foreach ($map as $arguments) {
                // Cannot use willReturnMap(), which uses ===
                if ($expr->equivalentTo($arguments[0])) {
                    return $arguments[1];
                }
            }

            return null;
        };
    }
}
