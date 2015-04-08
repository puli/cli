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
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Api\Repository\RepositoryManager;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;

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
    private static $saveCommand;

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
        self::$saveCommand = self::$application->getCommand('map')->getSubCommand('save');
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
vendor/root
/path1 res
/path2 res, assets

vendor/package1
/path3 resources

vendor/package2
/path4 Resources/css


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
/path1 res
/path2 res, assets

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
/path3 resources

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
vendor/root
/path1 res
/path2 res, assets

vendor/package1
/path3 resources


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
vendor/package1
/path3 resources

vendor/package2
/path4 Resources/css


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNoMappings()
    {
        $this->repoManager->expects($this->any())
            ->method('getPathMappings')
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

    public function testSaveNewMappingWithRelativePath()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('path res assets'));

        $this->repoManager->expects($this->once())
            ->method('hasPathMapping')
            ->with('/path')
            ->willReturn(false);

        $this->repoManager->expects($this->once())
            ->method('addPathMapping')
            ->with(new PathMapping('/path', array('res', 'assets')));

        $this->assertSame(0, $this->handler->handleSave($args));
    }

    public function testSaveNewMappingWithAbsolutePath()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('/path res assets'));

        $this->repoManager->expects($this->once())
            ->method('hasPathMapping')
            ->with('/path')
            ->willReturn(false);

        $this->repoManager->expects($this->once())
            ->method('addPathMapping')
            ->with(new PathMapping('/path', array('res', 'assets')));

        $this->assertSame(0, $this->handler->handleSave($args));
    }

    public function testReplaceMapping()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('/path res assets'));

        $this->repoManager->expects($this->once())
            ->method('hasPathMapping')
            ->with('/path')
            ->willReturn(true);

        $this->repoManager->expects($this->once())
            ->method('getPathMapping')
            ->with('/path')
            ->willReturn(new PathMapping('/path', array('previous')));

        $this->repoManager->expects($this->once())
            ->method('addPathMapping')
            ->with(new PathMapping('/path', array('res', 'assets')));

        $this->assertSame(0, $this->handler->handleSave($args));
    }

    public function testAddPathReference()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('/path +assets'));

        $this->repoManager->expects($this->once())
            ->method('hasPathMapping')
            ->with('/path')
            ->willReturn(true);

        $this->repoManager->expects($this->once())
            ->method('getPathMapping')
            ->with('/path')
            ->willReturn(new PathMapping('/path', array('res')));

        $this->repoManager->expects($this->once())
            ->method('addPathMapping')
            ->with(new PathMapping('/path', array('res', 'assets')));

        $this->assertSame(0, $this->handler->handleSave($args));
    }

    public function testRemovePathReference()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('/path -- -assets'));

        $this->repoManager->expects($this->once())
            ->method('hasPathMapping')
            ->with('/path')
            ->willReturn(true);

        $this->repoManager->expects($this->once())
            ->method('getPathMapping')
            ->with('/path')
            ->willReturn(new PathMapping('/path', array('res', 'assets')));

        $this->repoManager->expects($this->once())
            ->method('addPathMapping')
            ->with(new PathMapping('/path', array('res')));

        $this->assertSame(0, $this->handler->handleSave($args));
    }

    public function testRemoveAllPathReferences()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('/path -- -res -assets'));

        $this->repoManager->expects($this->once())
            ->method('hasPathMapping')
            ->with('/path')
            ->willReturn(true);

        $this->repoManager->expects($this->once())
            ->method('getPathMapping')
            ->with('/path')
            ->willReturn(new PathMapping('/path', array('res', 'assets')));

        $this->repoManager->expects($this->once())
            ->method('removePathMapping')
            ->with('/path');

        $this->assertSame(0, $this->handler->handleSave($args));
    }

    public function testDeleteMappingWithRelativePath()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('path'));

        $this->repoManager->expects($this->once())
            ->method('removePathMapping')
            ->with('/path');

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    public function testDeleteMappingWithAbsolutePath()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('/path'));

        $this->repoManager->expects($this->once())
            ->method('removePathMapping')
            ->with('/path');

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    private function initDefaultManager()
    {
        $this->repoManager->expects($this->any())
            ->method('getPathMappings')
            ->willReturnMap(array(
                array('vendor/root', null, array(
                    new PathMapping('/path1', 'res'),
                    new PathMapping('/path2', array('res', 'assets')),
                )),
                array('vendor/package1', null, array(
                    new PathMapping('/path3', 'resources'),
                )),
                array('vendor/package2', null, array(
                    new PathMapping('/path4', 'Resources/css'),
                )),
            ));

    }
}
