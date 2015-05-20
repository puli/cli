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
use Puli\Cli\Handler\MapCommandHandler;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Repository\PathConflict;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Api\Repository\PathMappingState;
use Puli\Manager\Api\Repository\RepositoryManager;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MapCommandHandlerTest extends AbstractCommandHandlerTest
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
     * @var PHPUnit_Framework_MockObject_MockObject|RepositoryManager
     */
    private $repoManager;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var MapCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$listCommand = self::$application->getCommand('map')->getSubCommand('list');
        self::$addCommand = self::$application->getCommand('map')->getSubCommand('add');
        self::$updateCommand = self::$application->getCommand('map')->getSubCommand('update');
        self::$deleteCommand = self::$application->getCommand('map')->getSubCommand('delete');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->repoManager = $this->getMock('Puli\Manager\Api\Repository\RepositoryManager');
        $this->packages = new PackageCollection(array(
            new RootPackage(new RootPackageFile('vendor/root'), '/root'),
            new Package(new PackageFile('vendor/package1'), '/package1'),
            new Package(new PackageFile('vendor/package2'), '/package2'),
        ));
        $this->handler = new MapCommandHandler($this->repoManager, $this->packages);
    }

    public function testListAllMappings()
    {
        $this->initDefaultManager();

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following path mappings are currently enabled:

    Package: vendor/root

        Puli Path      Real Path(s)
        /root/enabled  res, assets

    Package: vendor/package1

        Puli Path          Real Path(s)
        /package1/enabled  res, @vendor/package2:res

    Package: vendor/package2

        Puli Path          Real Path(s)
        /package2/enabled  res

The target paths of the following path mappings were not found:

    Package: vendor/root

        Puli Path        Real Path(s)
        /root/not-found  res

    Package: vendor/package1

        Puli Path            Real Path(s)
        /package1/not-found  res

    Package: vendor/package2

        Puli Path            Real Path(s)
        /package2/not-found  res

Some path mappings have conflicting paths:
 (add the package names to the "override-order" key in puli.json to resolve)

    Conflicting path: /conflict1

        Mapped by the following mappings:

        Package          Puli Path   Real Path(s)
        vendor/root      /conflict1  res, assets
        vendor/package1  /conflict1  res, @vendor/package2:res
        vendor/package2  /conflict1  res

    Conflicting path: /conflict2/sub/path

        Mapped by the following mappings:

        Package          Puli Path   Real Path(s)
        vendor/package1  /conflict2  res
        vendor/package2  /conflict2  res


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListRootPackageMappings()
    {
        $this->initDefaultManager();

        $args = self::$listCommand->parseArgs(new StringArgs('--root'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following path mappings are currently enabled:

    Puli Path      Real Path(s)
    /root/enabled  res, assets

The target paths of the following path mappings were not found:

    Puli Path        Real Path(s)
    /root/not-found  res

Some path mappings have conflicting paths:
 (add the package names to the "override-order" key in puli.json to resolve)

    Conflicting path: /conflict1

        Mapped by the following mappings:

        Package          Puli Path   Real Path(s)
        vendor/root      /conflict1  res, assets
        vendor/package1  /conflict1  res, @vendor/package2:res
        vendor/package2  /conflict1  res


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListPackageMappings()
    {
        $this->initDefaultManager();

        $args = self::$listCommand->parseArgs(new StringArgs('--package vendor/package1'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following path mappings are currently enabled:

    Puli Path          Real Path(s)
    /package1/enabled  res, @vendor/package2:res

The target paths of the following path mappings were not found:

    Puli Path            Real Path(s)
    /package1/not-found  res

Some path mappings have conflicting paths:
 (add the package names to the "override-order" key in puli.json to resolve)

    Conflicting path: /conflict1

        Mapped by the following mappings:

        Package          Puli Path   Real Path(s)
        vendor/root      /conflict1  res, assets
        vendor/package1  /conflict1  res, @vendor/package2:res
        vendor/package2  /conflict1  res

    Conflicting path: /conflict2/sub/path

        Mapped by the following mappings:

        Package          Puli Path   Real Path(s)
        vendor/package1  /conflict2  res
        vendor/package2  /conflict2  res


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListRootAndPackageMappings()
    {
        $this->initDefaultManager();

        $args = self::$listCommand->parseArgs(new StringArgs('--root --package vendor/package1'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following path mappings are currently enabled:

    Package: vendor/root

        Puli Path      Real Path(s)
        /root/enabled  res, assets

    Package: vendor/package1

        Puli Path          Real Path(s)
        /package1/enabled  res, @vendor/package2:res

The target paths of the following path mappings were not found:

    Package: vendor/root

        Puli Path        Real Path(s)
        /root/not-found  res

    Package: vendor/package1

        Puli Path            Real Path(s)
        /package1/not-found  res

Some path mappings have conflicting paths:
 (add the package names to the "override-order" key in puli.json to resolve)

    Conflicting path: /conflict1

        Mapped by the following mappings:

        Package          Puli Path   Real Path(s)
        vendor/root      /conflict1  res, assets
        vendor/package1  /conflict1  res, @vendor/package2:res
        vendor/package2  /conflict1  res

    Conflicting path: /conflict2/sub/path

        Mapped by the following mappings:

        Package          Puli Path   Real Path(s)
        vendor/package1  /conflict2  res
        vendor/package2  /conflict2  res


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListMultiplePackageMappings()
    {
        $this->initDefaultManager();

        $args = self::$listCommand->parseArgs(new StringArgs('--package vendor/package1 --package vendor/package2'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following path mappings are currently enabled:

    Package: vendor/package1

        Puli Path          Real Path(s)
        /package1/enabled  res, @vendor/package2:res

    Package: vendor/package2

        Puli Path          Real Path(s)
        /package2/enabled  res

The target paths of the following path mappings were not found:

    Package: vendor/package1

        Puli Path            Real Path(s)
        /package1/not-found  res

    Package: vendor/package2

        Puli Path            Real Path(s)
        /package2/not-found  res

Some path mappings have conflicting paths:
 (add the package names to the "override-order" key in puli.json to resolve)

    Conflicting path: /conflict1

        Mapped by the following mappings:

        Package          Puli Path   Real Path(s)
        vendor/root      /conflict1  res, assets
        vendor/package1  /conflict1  res, @vendor/package2:res
        vendor/package2  /conflict1  res

    Conflicting path: /conflict2/sub/path

        Mapped by the following mappings:

        Package          Puli Path   Real Path(s)
        vendor/package1  /conflict2  res
        vendor/package2  /conflict2  res


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledMappings()
    {
        $this->initDefaultManager();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Package: vendor/root

    Puli Path      Real Path(s)
    /root/enabled  res, assets

Package: vendor/package1

    Puli Path          Real Path(s)
    /package1/enabled  res, @vendor/package2:res

Package: vendor/package2

    Puli Path          Real Path(s)
    /package2/enabled  res


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNotFoundMappings()
    {
        $this->initDefaultManager();

        $args = self::$listCommand->parseArgs(new StringArgs('--not-found'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Package: vendor/root

    Puli Path        Real Path(s)
    /root/not-found  res

Package: vendor/package1

    Puli Path            Real Path(s)
    /package1/not-found  res

Package: vendor/package2

    Puli Path            Real Path(s)
    /package2/not-found  res


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListConflictingMappings()
    {
        $this->initDefaultManager();

        $args = self::$listCommand->parseArgs(new StringArgs('--conflict'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Conflicting path: /conflict1

    Mapped by the following mappings:

    Package          Puli Path   Real Path(s)
    vendor/root      /conflict1  res, assets
    vendor/package1  /conflict1  res, @vendor/package2:res
    vendor/package2  /conflict1  res

Conflicting path: /conflict2/sub/path

    Mapped by the following mappings:

    Package          Puli Path   Real Path(s)
    vendor/package1  /conflict2  res
    vendor/package2  /conflict2  res


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledAndNotFoundMappings()
    {
        $this->initDefaultManager();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --not-found'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
The following path mappings are currently enabled:

    Package: vendor/root

        Puli Path      Real Path(s)
        /root/enabled  res, assets

    Package: vendor/package1

        Puli Path          Real Path(s)
        /package1/enabled  res, @vendor/package2:res

    Package: vendor/package2

        Puli Path          Real Path(s)
        /package2/enabled  res

The target paths of the following path mappings were not found:

    Package: vendor/root

        Puli Path        Real Path(s)
        /root/not-found  res

    Package: vendor/package1

        Puli Path            Real Path(s)
        /package1/not-found  res

    Package: vendor/package2

        Puli Path            Real Path(s)
        /package2/not-found  res


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledMappingsFromRoot()
    {
        $this->initDefaultManager();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --root'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Puli Path      Real Path(s)
/root/enabled  res, assets

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledMappingsFromPackage()
    {
        $this->initDefaultManager();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --package vendor/package1'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Puli Path          Real Path(s)
/package1/enabled  res, @vendor/package2:res

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNoMappings()
    {
        $this->repoManager->expects($this->any())
            ->method('getRootPathMappings')
            ->willReturn(array());

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
No path mappings. Use "puli map <path> <file>" to map a Puli path to a file or directory.

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testAddMappingWithRelativePath()
    {
        $args = self::$addCommand->parseArgs(new StringArgs('path1 res assets'));

        $this->repoManager->expects($this->once())
            ->method('addRootPathMapping')
            ->with(new PathMapping('/path1', array('res', 'assets')));

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddMappingWithAbsolutePath()
    {
        $args = self::$addCommand->parseArgs(new StringArgs('/path1 res assets'));

        $this->repoManager->expects($this->once())
            ->method('addRootPathMapping')
            ->with(new PathMapping('/path1', array('res', 'assets')));

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddMappingForce()
    {
        $args = self::$addCommand->parseArgs(new StringArgs('--force /path res'));

        $this->repoManager->expects($this->once())
            ->method('addRootPathMapping')
            ->with(new PathMapping('/path', array('res')), RepositoryManager::OVERRIDE | RepositoryManager::IGNORE_FILE_NOT_FOUND);

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testUpdateMappingAddPathReferences()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('/path --add assets --add res'));

        $mapping = new PathMapping('/path', array('previous'));
        $mapping->load($this->packages->getRootPackage(), $this->packages);

        $this->repoManager->expects($this->once())
            ->method('getRootPathMapping')
            ->with('/path')
            ->willReturn($mapping);

        $this->repoManager->expects($this->once())
            ->method('addRootPathMapping')
            ->with(new PathMapping('/path', array('previous', 'assets', 'res')), RepositoryManager::OVERRIDE);

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateMappingRemovePathReference()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('/path --remove assets'));

        $mapping = new PathMapping('/path', array('assets', 'res'));
        $mapping->load($this->packages->getRootPackage(), $this->packages);

        $this->repoManager->expects($this->once())
            ->method('getRootPathMapping')
            ->with('/path')
            ->willReturn($mapping);

        $this->repoManager->expects($this->once())
            ->method('addRootPathMapping')
            ->with(new PathMapping('/path', array('res')), RepositoryManager::OVERRIDE);

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateMappingRemoveAllPathReferences()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('/path --remove assets --remove res'));

        $mapping = new PathMapping('/path', array('assets', 'res'));
        $mapping->load($this->packages->getRootPackage(), $this->packages);

        $this->repoManager->expects($this->once())
            ->method('getRootPathMapping')
            ->with('/path')
            ->willReturn($mapping);

        $this->repoManager->expects($this->once())
            ->method('removeRootPathMapping')
            ->with('/path');

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateMappingRelativePath()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('rel --add assets'));

        $mapping = new PathMapping('/rel', array('previous'));
        $mapping->load($this->packages->getRootPackage(), $this->packages);

        $this->repoManager->expects($this->once())
            ->method('getRootPathMapping')
            ->with('/rel')
            ->willReturn($mapping);

        $this->repoManager->expects($this->once())
            ->method('addRootPathMapping')
            ->with(new PathMapping('/rel', array('previous', 'assets')), RepositoryManager::OVERRIDE);

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateMappingForce()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('/path --add assets --force'));

        $mapping = new PathMapping('/path', array('previous'));
        $mapping->load($this->packages->getRootPackage(), $this->packages);

        $this->repoManager->expects($this->once())
            ->method('getRootPathMapping')
            ->with('/path')
            ->willReturn($mapping);

        $this->repoManager->expects($this->once())
            ->method('addRootPathMapping')
            ->with(new PathMapping('/path', array('previous', 'assets')), RepositoryManager::OVERRIDE | RepositoryManager::IGNORE_FILE_NOT_FOUND);

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testUpdateMappingFailsIfNoChange()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('/path'));

        $mapping = new PathMapping('/path', array('previous'));
        $mapping->load($this->packages->getRootPackage(), $this->packages);

        $this->repoManager->expects($this->once())
            ->method('getRootPathMapping')
            ->with('/path')
            ->willReturn($mapping);

        $this->repoManager->expects($this->never())
            ->method('addRootPathMapping');

        $this->handler->handleUpdate($args);
    }

    public function testDeleteMappingWithRelativePath()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('path1'));

        $this->repoManager->expects($this->once())
            ->method('hasRootPathMapping')
            ->with('/path1')
            ->willReturn(true);

        $this->repoManager->expects($this->once())
            ->method('removeRootPathMapping')
            ->with('/path1');

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    public function testDeleteMappingWithAbsolutePath()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('/path1'));

        $this->repoManager->expects($this->once())
            ->method('hasRootPathMapping')
            ->with('/path1')
            ->willReturn(true);

        $this->repoManager->expects($this->once())
            ->method('removeRootPathMapping')
            ->with('/path1');

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The path "/path1" is not mapped in the package "vendor/root".
     */
    public function testDeleteMappingFailsIfNotFound()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('/path1'));

        $this->repoManager->expects($this->once())
            ->method('hasRootPathMapping')
            ->with('/path1')
            ->willReturn(false);

        $this->repoManager->expects($this->never())
            ->method('removeRootPathMapping')
            ->with('/path1');

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    private function initDefaultManager()
    {
        $conflictMappingRoot1 = new PathMapping('/conflict1', array('res', 'assets'));
        $conflictMappingPackage11 = new PathMapping('/conflict1', array('res', '@vendor/package2:res'));
        $conflictMappingPackage12 = new PathMapping('/conflict2', 'res');
        $conflictMappingPackage21 = new PathMapping('/conflict1', 'res');
        $conflictMappingPackage22 = new PathMapping('/conflict2', 'res');

        $conflictMappingRoot1->load($this->packages->getRootPackage(), $this->packages);
        $conflictMappingPackage11->load($this->packages->get('vendor/package1'), $this->packages);
        $conflictMappingPackage12->load($this->packages->get('vendor/package1'), $this->packages);
        $conflictMappingPackage21->load($this->packages->get('vendor/package2'), $this->packages);
        $conflictMappingPackage22->load($this->packages->get('vendor/package2'), $this->packages);

        $conflict1 = new PathConflict('/conflict1');
        $conflict1->addMappings(array(
            $conflictMappingRoot1,
            $conflictMappingPackage11,
            $conflictMappingPackage21,
        ));

        $conflict2 = new PathConflict('/conflict2/sub/path');
        $conflict2->addMappings(array(
            $conflictMappingPackage12,
            $conflictMappingPackage22,
        ));

        $this->repoManager->expects($this->any())
            ->method('findPathMappings')
            ->willReturnCallback($this->returnFromMap(array(
                array($this->packageAndState('vendor/root', PathMappingState::ENABLED), array(
                    new PathMapping('/root/enabled', array('res', 'assets')),
                )),
                array($this->packageAndState('vendor/package1', PathMappingState::ENABLED), array(
                    new PathMapping('/package1/enabled', array('res', '@vendor/package2:res')),
                )),
                array($this->packageAndState('vendor/package2', PathMappingState::ENABLED), array(
                    new PathMapping('/package2/enabled', 'res'),
                )),
                array($this->packageAndState('vendor/root', PathMappingState::NOT_FOUND), array(
                    new PathMapping('/root/not-found', 'res'),
                )),
                array($this->packageAndState('vendor/package1', PathMappingState::NOT_FOUND), array(
                    new PathMapping('/package1/not-found', 'res'),
                )),
                array($this->packageAndState('vendor/package2', PathMappingState::NOT_FOUND), array(
                    new PathMapping('/package2/not-found', 'res'),
                )),
                array($this->packagesAndState(array('vendor/root'), PathMappingState::CONFLICT), array(
                    $conflictMappingRoot1,
                )),
                array($this->packagesAndState(array('vendor/package1'), PathMappingState::CONFLICT), array(
                    $conflictMappingPackage11,
                    $conflictMappingPackage12,
                )),
                array($this->packagesAndState(array('vendor/package2'), PathMappingState::CONFLICT), array(
                    $conflictMappingPackage21,
                    $conflictMappingPackage22,
                )),
                array($this->packagesAndState(array('vendor/root', 'vendor/package1'), PathMappingState::CONFLICT), array(
                    $conflictMappingRoot1,
                    $conflictMappingPackage11,
                    $conflictMappingPackage12,
                )),
                array($this->packagesAndState(array('vendor/root', 'vendor/package2'), PathMappingState::CONFLICT), array(
                    $conflictMappingRoot1,
                    $conflictMappingPackage21,
                    $conflictMappingPackage22,
                )),
                array($this->packagesAndState(array('vendor/package1', 'vendor/package2'), PathMappingState::CONFLICT), array(
                    $conflictMappingPackage11,
                    $conflictMappingPackage12,
                    $conflictMappingPackage21,
                    $conflictMappingPackage22,
                )),
                array($this->packagesAndState(array('vendor/root', 'vendor/package1', 'vendor/package2'), PathMappingState::CONFLICT), array(
                    $conflictMappingRoot1,
                    $conflictMappingPackage11,
                    $conflictMappingPackage12,
                    $conflictMappingPackage21,
                    $conflictMappingPackage22,
                )),
            )));
    }

    private function packageAndState($packageName, $state)
    {
        return Expr::same($packageName, PathMapping::CONTAINING_PACKAGE)
            ->andSame($state, PathMapping::STATE);
    }

    private function packagesAndState(array $packageNames, $state)
    {
        return Expr::in($packageNames, PathMapping::CONTAINING_PACKAGE)
            ->andSame($state, PathMapping::STATE);
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
