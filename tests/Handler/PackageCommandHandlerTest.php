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
use Puli\RepositoryManager\Api\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Api\Package\InstallInfo;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Package\PackageFile;
use Puli\RepositoryManager\Api\Package\PackageManager;
use Puli\RepositoryManager\Api\Package\PackageState;
use Puli\RepositoryManager\Api\Package\RootPackage;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use RuntimeException;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * @since  1.0
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
    private static $removeCommand;

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
        self::$removeCommand = self::$application->getCommand('package')->getSubCommand('remove');
        self::$cleanCommand = self::$application->getCommand('package')->getSubCommand('clean');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->environment = $this->getMockBuilder('Puli\RepositoryManager\Api\Environment\ProjectEnvironment')
            ->disableOriginalConstructor()
            ->getMock();
        $this->packageManager = $this->getMock('Puli\RepositoryManager\Api\Package\PackageManager');
        $this->handler = new PackageCommandHandler($this->packageManager);

        $this->environment->expects($this->any())
            ->method('getRootDirectory')
            ->willReturn('/root');

        $this->packageManager->expects($this->any())
            ->method('getEnvironment')
            ->willReturn($this->environment);

        $installInfo1 = new InstallInfo('vendor/package1', 'packages/package1');
        $installInfo2 = new InstallInfo('vendor/package2', 'packages/package2');
        $installInfo3 = new InstallInfo('vendor/package3', 'packages/package3');
        $installInfo4 = new InstallInfo('vendor/package4', 'packages/package4');

        $installInfo1->setInstallerName('spock');
        $installInfo2->setInstallerName('spock');
        $installInfo3->setInstallerName('kirk');
        $installInfo4->setInstallerName('spock');

        $rootPackage = new RootPackage(new RootPackageFile('vendor/root'), '/root');
        $package1 = new Package(new PackageFile('vendor/package1'), '/package1', $installInfo1);
        $package2 = new Package(new PackageFile('vendor/package2'), '/package2', $installInfo2);
        $package3 = new Package(new PackageFile('vendor/package3'), '/package3', $installInfo3);
        $package4 = new Package(null, '/package4', $installInfo4, array(new RuntimeException('Load error')));

        $this->packageManager->expects($this->any())
            ->method('findPackages')
            ->willReturnCallback($this->returnFromMap(array(
                array($this->state(PackageState::ENABLED), new PackageCollection(array($rootPackage, $package1, $package2))),
                array($this->state(PackageState::NOT_FOUND), new PackageCollection(array($package3))),
                array($this->state(PackageState::NOT_LOADABLE), new PackageCollection(array($package4))),
                array($this->installerAndState('spock', PackageState::ENABLED), new PackageCollection(array($package1, $package2))),
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
Enabled packages:

    vendor/package1 spock packages/package1
    vendor/package2 spock packages/package2
    vendor/root

The following packages could not be found:
 (use "puli package clean" to remove)

    vendor/package3 kirk packages/package3

The following packages could not be loaded:

    vendor/package4: RuntimeException: Load error


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
Enabled packages:

    vendor/package1 spock packages/package1
    vendor/package2 spock packages/package2

The following packages could not be loaded:

    vendor/package4: RuntimeException: Load error


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
vendor/package1 spock packages/package1
vendor/package2 spock packages/package2
vendor/root

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
vendor/package3 kirk packages/package3

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
vendor/package4: RuntimeException: Load error

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
Enabled packages:

    vendor/package1 spock packages/package1
    vendor/package2 spock packages/package2
    vendor/root

The following packages could not be found:
 (use "puli package clean" to remove)

    vendor/package3 kirk packages/package3


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
vendor/package1 spock packages/package1
vendor/package2 spock packages/package2

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
            ->with($this->wd.'/packages/package1', null, InstallInfo::DEFAULT_INSTALLER_NAME);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testInstallPackageWithAbsolutePath()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('/packages/package1'));

        $this->packageManager->expects($this->once())
            ->method('installPackage')
            ->with('/packages/package1', null, InstallInfo::DEFAULT_INSTALLER_NAME);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testInstallPackageWithCustomName()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('/packages/package1 custom/package1'));

        $this->packageManager->expects($this->once())
            ->method('installPackage')
            ->with('/packages/package1', 'custom/package1', InstallInfo::DEFAULT_INSTALLER_NAME);

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testInstallPackageWithCustomInstaller()
    {
        $args = self::$installCommand->parseArgs(new StringArgs('--installer kirk /packages/package1'));

        $this->packageManager->expects($this->once())
            ->method('installPackage')
            ->with('/packages/package1', null, 'kirk');

        $this->assertSame(0, $this->handler->handleInstall($args));
    }

    public function testRemovePackage()
    {
        $args = self::$removeCommand->parseArgs(new StringArgs('vendor/package1'));

        $this->packageManager->expects($this->once())
            ->method('removePackage')
            ->with('vendor/package1');

        $this->assertSame(0, $this->handler->handleRemove($args));
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

    private function state($state)
    {
        return Expr::same(Package::STATE, $state);
    }

    private function installerAndState($installer, $state)
    {
        return Expr::same(Package::INSTALLER, $installer)
            ->andSame(Package::STATE, $state);
    }

    private function returnFromMap(array $map)
    {
        return function (Expression $expr) use ($map) {
            foreach ($map as $arguments) {
                // Cannot use willReturnMap(), which uses ===
                if ($expr->equals($arguments[0])) {
                    return $arguments[1];
                }
            }

            return null;
        };
    }
}
