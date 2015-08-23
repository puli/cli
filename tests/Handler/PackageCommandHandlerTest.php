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
use Puli\Cli\Handler\PackageCommandHandler;
use Puli\Manager\Api\Environment\ProjectEnvironment;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageManager;
use Puli\Manager\Api\Package\PackageState;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use RuntimeException;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageCommandHandlerTest extends AbstractCommandHandlerTest
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
     * @var PHPUnit_Framework_MockObject_MockObject|ProjectEnvironment
     */
    private $environment;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|PackageManager
     */
    private $packageManager;

    /**
     * @var PackageCommandHandler
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

        self::$listCommand = self::$application->getCommand('package')->getSubCommand('list');
        self::$installCommand = self::$application->getCommand('package')->getSubCommand('install');
        self::$renameCommand = self::$application->getCommand('package')->getSubCommand('rename');
        self::$deleteCommand = self::$application->getCommand('package')->getSubCommand('delete');
        self::$cleanCommand = self::$application->getCommand('package')->getSubCommand('clean');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->environment = $this->getMockBuilder('Puli\Manager\Api\Environment\ProjectEnvironment')
            ->disableOriginalConstructor()
            ->getMock();
        $this->packageManager = $this->getMock('Puli\Manager\Api\Package\PackageManager');
        $this->handler = new PackageCommandHandler($this->packageManager);

        $this->environment->expects($this->any())
            ->method('getRootDirectory')
            ->willReturn(__DIR__.'/Fixtures/root');

        $this->packageManager->expects($this->any())
            ->method('getEnvironment')
            ->willReturn($this->environment);

        $installInfo1 = new InstallInfo('vendor/package1', 'packages/package1');
        $installInfo2 = new InstallInfo('vendor/package2', 'packages/package2');
        $installInfo3 = new InstallInfo('vendor/package3', 'packages/package3');
        $installInfo4 = new InstallInfo('vendor/package4', 'packages/package4');
        $installInfo5 = new InstallInfo('vendor/package5', 'packages/package5');

        $installInfo1->setInstallerName('spock');
        $installInfo2->setInstallerName('spock');
        $installInfo3->setInstallerName('kirk');
        $installInfo4->setInstallerName('spock');
        $installInfo5->setInstallerName('spock');
        $installInfo5->setDev(true);

        $rootPackage = new RootPackage(new RootPackageFile('vendor/root'), __DIR__.'/Fixtures/root');
        $package1 = new Package(new PackageFile('vendor/package1'), __DIR__.'/Fixtures/root/packages/package1', $installInfo1);
        $package2 = new Package(new PackageFile('vendor/package2'), __DIR__.'/Fixtures/root/packages/package2', $installInfo2);
        $package3 = new Package(new PackageFile('vendor/package3'), __DIR__.'/Fixtures/root/packages/package3', $installInfo3);
        $package4 = new Package(null, __DIR__.'/Fixtures/root/packages/package4', $installInfo4, array(new RuntimeException('Load error')));
        $package5 = new Package(new PackageFile('vendor/package5'), __DIR__.'/Fixtures/root/packages/package5', $installInfo5);

        $this->packageManager->expects($this->any())
            ->method('findPackages')
            ->willReturnCallback($this->returnFromMap(array(
                array($this->all(), new PackageCollection(array($rootPackage, $package1, $package2, $package3, $package4, $package5))),
                array($this->dev(array(true)), new PackageCollection(array($package5))),
                array($this->dev(array(false)), new PackageCollection(array($rootPackage, $package1, $package2, $package3, $package4))),
                array($this->dev(array(true, false)), new PackageCollection(array($rootPackage, $package1, $package2, $package3, $package4, $package5))),
                array($this->installer('spock'), new PackageCollection(array($package1, $package2, $package4, $package5))),
                array($this->state(PackageState::ENABLED), new PackageCollection(array($rootPackage, $package1, $package2, $package5))),
                array($this->state(PackageState::NOT_FOUND), new PackageCollection(array($package3))),
                array($this->state(PackageState::NOT_LOADABLE), new PackageCollection(array($package4))),
                array($this->states(array(PackageState::ENABLED, PackageState::NOT_FOUND)), new PackageCollection(array($rootPackage, $package1, $package2, $package3, $package5))),
                array($this->installerAndState('spock', PackageState::ENABLED), new PackageCollection(array($package1, $package2, $package5))),
                array($this->installerAndState('spock', PackageState::NOT_FOUND), new PackageCollection(array())),
                array($this->installerAndState('spock', PackageState::NOT_LOADABLE), new PackageCollection(array($package4))),
            )));

        $this->previousWd = getcwd();
        $this->wd = __DIR__;

        chdir($this->wd);
    }

    protected function tearDown()
    {
        chdir($this->previousWd);
    }

    public function testListPackages()
    {
        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following packages are currently enabled:

    Package Name     Installer  Dev  Install Path
    vendor/package1  spock      no   packages/package1
    vendor/package2  spock      no   packages/package2
    vendor/package5  spock      yes  packages/package5
    vendor/root                      .

The following packages could not be found:
 (use "puli package --clean" to remove)

    Package Name     Installer  Dev  Install Path
    vendor/package3  kirk       no   packages/package3

The following packages could not be loaded:

    Package Name     Error
    vendor/package4  RuntimeException: Load error


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListPackagesByInstaller()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--installer spock'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following packages are currently enabled:

    Package Name     Installer  Dev  Install Path
    vendor/package1  spock      no   packages/package1
    vendor/package2  spock      no   packages/package2
    vendor/package5  spock      yes  packages/package5

The following packages could not be loaded:

    Package Name     Error
    vendor/package4  RuntimeException: Load error


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledPackages()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Package Name     Installer  Dev  Install Path
vendor/package1  spock      no   packages/package1
vendor/package2  spock      no   packages/package2
vendor/package5  spock      yes  packages/package5
vendor/root                      .

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNotFoundPackages()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--not-found'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Package Name     Installer  Dev  Install Path
vendor/package3  kirk       no   packages/package3

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNotLoadablePackages()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--not-loadable'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Package Name     Error
vendor/package4  RuntimeException: Load error

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledAndNotFoundPackages()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --not-found'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following packages are currently enabled:

    Package Name     Installer  Dev  Install Path
    vendor/package1  spock      no   packages/package1
    vendor/package2  spock      no   packages/package2
    vendor/package5  spock      yes  packages/package5
    vendor/root                      .

The following packages could not be found:
 (use "puli package --clean" to remove)

    Package Name     Installer  Dev  Install Path
    vendor/package3  kirk       no   packages/package3


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledPackagesByInstaller()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --installer spock'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Package Name     Installer  Dev  Install Path
vendor/package1  spock      no   packages/package1
vendor/package2  spock      no   packages/package2
vendor/package5  spock      yes  packages/package5

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListDevPackages()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--dev'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following packages are currently enabled:

    Package Name     Installer  Dev  Install Path
    vendor/package5  spock      yes  packages/package5


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNoDevPackages()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--no-dev'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following packages are currently enabled:

    Package Name     Installer  Dev  Install Path
    vendor/package1  spock      no   packages/package1
    vendor/package2  spock      no   packages/package2
    vendor/root                      .

The following packages could not be found:
 (use "puli package --clean" to remove)

    Package Name     Installer  Dev  Install Path
    vendor/package3  kirk       no   packages/package3

The following packages could not be loaded:

    Package Name     Error
    vendor/package4  RuntimeException: Load error


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListDevAndNoDevPackages()
    {
        // Same as if passing none of the too
        // Does not make much sense but could occur if building the
        // "package list" command dynamically
        $args = self::$listCommand->parseArgs(new StringArgs('--dev --no-dev'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following packages are currently enabled:

    Package Name     Installer  Dev  Install Path
    vendor/package1  spock      no   packages/package1
    vendor/package2  spock      no   packages/package2
    vendor/package5  spock      yes  packages/package5
    vendor/root                      .

The following packages could not be found:
 (use "puli package --clean" to remove)

    Package Name     Installer  Dev  Install Path
    vendor/package3  kirk       no   packages/package3

The following packages could not be loaded:

    Package Name     Error
    vendor/package4  RuntimeException: Load error


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListPackagesWithFormat()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--format %name%:%installer%:%install_path%:%state%:%dev%'));

        $rootDir = $this->environment->getRootDirectory();
        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root::$rootDir:enabled:false
vendor/package1:spock:$rootDir/packages/package1:enabled:false
vendor/package2:spock:$rootDir/packages/package2:enabled:false
vendor/package3:kirk:$rootDir/packages/package3:not-found:false
vendor/package4:spock:$rootDir/packages/package4:not-loadable:false
vendor/package5:spock:$rootDir/packages/package5:enabled:true

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testInstallPackageWithRelativePath()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('packages/package1'));

        $this->packageManager->expects($this->once())
            ->method('installPackage')
            ->with($this->wd.'/packages/package1', null, InstallInfo::DEFAULT_INSTALLER_NAME, false);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testInstallPackageWithAbsolutePath()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('/packages/package1'));

        $this->packageManager->expects($this->once())
            ->method('installPackage')
            ->with('/packages/package1', null, InstallInfo::DEFAULT_INSTALLER_NAME, false);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testInstallPackageWithCustomName()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('/packages/package1 custom/package1'));

        $this->packageManager->expects($this->once())
            ->method('installPackage')
            ->with('/packages/package1', 'custom/package1', InstallInfo::DEFAULT_INSTALLER_NAME, false);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testInstallPackageWithCustomInstaller()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('--installer kirk /packages/package1'));

        $this->packageManager->expects($this->once())
            ->method('installPackage')
            ->with('/packages/package1', null, 'kirk', false);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testInstallDevPackage()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('--dev packages/package1'));

        $this->packageManager->expects($this->once())
            ->method('installPackage')
            ->with($this->wd.'/packages/package1', null, InstallInfo::DEFAULT_INSTALLER_NAME, true);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testRenamePackage()
    {
        $args = self::$renameCommand->parseArgs(new StringArgs('vendor/package1 vendor/new'));

        $this->packageManager->expects($this->once())
            ->method('renamePackage')
            ->with('vendor/package1', 'vendor/new');

        $this->assertSame(0, $this->handler->handleRename($args));
    }

    public function testDeletePackage()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('vendor/package1'));

        $this->packageManager->expects($this->once())
            ->method('hasPackage')
            ->with('vendor/package1')
            ->willReturn(true);

        $this->packageManager->expects($this->once())
            ->method('removePackage')
            ->with('vendor/package1');

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The package "vendor/package1" is not installed.
     */
    public function testDeletePackageFailsIfNotFound()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('vendor/package1'));

        $this->packageManager->expects($this->once())
            ->method('hasPackage')
            ->with('vendor/package1')
            ->willReturn(false);

        $this->packageManager->expects($this->never())
            ->method('removePackage')
            ->with('vendor/package1');

        $this->handler->handleDelete($args);
    }

    public function testCleanPackages()
    {
        $args = self::$cleanCommand->parseArgs(new StringArgs(''));

        // The not-found package
        $this->packageManager->expects($this->once())
            ->method('removePackage')
            ->with('vendor/package3');

        $expected = <<<EOF
Removing vendor/package3

EOF;

        $this->assertSame(0, $this->handler->handleClean($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    private function all()
    {
        return Expr::true();
    }

    private function dev(array $dev)
    {
        return Expr::in($dev, Package::DEV);
    }

    private function state($state)
    {
        return Expr::same($state, Package::STATE);
    }

    private function states(array $states)
    {
        return Expr::in($states, Package::STATE);
    }

    private function installer($installer)
    {
        return Expr::same($installer, Package::INSTALLER);
    }

    private function installerAndState($installer, $state)
    {
        return Expr::same($installer, Package::INSTALLER)
            ->andSame($state, Package::STATE);
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
