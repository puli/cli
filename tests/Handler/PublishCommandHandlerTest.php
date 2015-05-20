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

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\Cli\Handler\PublishCommandHandler;
use Puli\Manager\Api\Asset\AssetManager;
use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Installation\InstallationManager;
use Puli\Manager\Api\Installation\InstallationParams;
use Puli\Manager\Api\Installer\InstallerDescriptor;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Api\Server\ServerManager;
use Puli\Manager\Tests\TestException;
use Puli\Repository\Resource\Collection\ArrayResourceCollection;
use Puli\Repository\Resource\GenericResource;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PublishCommandHandlerTest extends AbstractCommandHandlerTest
{
    const UUID1 = 'e81b32f4-5851-4955-bea7-c90382112cba';

    const UUID2 = '33dbec79-aa8a-48ad-a15e-24e20799075d';

    const UUID3 = '49cfdf53-4720-4548-88d3-564a9faccdc6';

    const UUID4 = '8c64be21-7a1a-4ea8-9a68-5081a54249ef';

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
     * @var Command
     */
    private static $installCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|AssetManager
     */
    private $assetManager;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|InstallationManager
     */
    private $installationManager;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ServerManager
     */
    private $serverManager;

    /**
     * @var PublishCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$listCommand = self::$application->getCommand('publish')->getSubCommand('list');
        self::$addCommand = self::$application->getCommand('publish')->getSubCommand('add');
        self::$updateCommand = self::$application->getCommand('publish')->getSubCommand('update');
        self::$deleteCommand = self::$application->getCommand('publish')->getSubCommand('delete');
        self::$installCommand = self::$application->getCommand('publish')->getSubCommand('install');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->assetManager = $this->getMock('Puli\Manager\Api\Asset\AssetManager');
        $this->installationManager = $this->getMock('Puli\Manager\Api\Installation\InstallationManager');
        $this->serverManager = $this->getMock('Puli\Manager\Api\Server\ServerManager');
        $this->handler = new PublishCommandHandler($this->assetManager, $this->installationManager, $this->serverManager);
    }

    public function testListMappings()
    {
        $localServer = new Server('localhost', 'symlink', 'web', '/%s');
        $remoteServer = new Server('example.com', 'rsync', 'ssh://example.com', 'http://example.com/%s');

        $this->serverManager->expects($this->any())
            ->method('hasServer')
            ->willReturnMap(array(
                array('localhost', true),
                array('example.com', true),
            ));

        $this->serverManager->expects($this->any())
            ->method('getServer')
            ->willReturnMap(array(
                array('localhost', $localServer),
                array('example.com', $remoteServer),
            ));

        $this->assetManager->expects($this->once())
            ->method('getAssetMappings')
            ->willReturn(array(
                new AssetMapping('/app/public', 'localhost', '/', Uuid::fromString(self::UUID1)),
                new AssetMapping('/acme/blog/public', 'example.com', '/blog', Uuid::fromString(self::UUID2)),
                new AssetMapping('/acme/profiler/public', 'localhost', '/profiler', Uuid::fromString(self::UUID3)),
            ));

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $expected = <<<EOF
The following web assets are currently enabled:

    Server localhost
    Location:   web
    Installer:  symlink
    URL Format: /%s

        e81b32 /app/public           /
        49cfdf /acme/profiler/public /profiler

    Server example.com
    Location:   ssh://example.com
    Installer:  rsync
    URL Format: http://example.com/%s

        33dbec /acme/blog/public /blog

Use "puli asset install" to install the assets on your servers.

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListMappingsOfNonExistingServer()
    {
        $Server = new Server('example.com', 'rsync', 'ssh://example.com', 'http://example.com/%s');

        $this->serverManager->expects($this->any())
            ->method('hasServer')
            ->willReturnMap(array(
                array('localhost', false),
                array('example.com', true),
            ));

        $this->serverManager->expects($this->any())
            ->method('getServer')
            ->willReturnMap(array(
                array('example.com', $Server),
            ));

        $this->assetManager->expects($this->once())
            ->method('getAssetMappings')
            ->willReturn(array(
                new AssetMapping('/app/public', 'localhost', '/', Uuid::fromString(self::UUID1)),
                new AssetMapping('/acme/blog/public', 'example.com', '/blog', Uuid::fromString(self::UUID2)),
                new AssetMapping('/acme/profiler/public', 'localhost', '/profiler', Uuid::fromString(self::UUID3)),
            ));

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $expected = <<<EOF
The following web assets are currently enabled:

    Server example.com
    Location:   ssh://example.com
    Installer:  rsync
    URL Format: http://example.com/%s

        33dbec /acme/blog/public /blog

Use "puli asset install" to install the assets on your servers.

The following web assets are disabled since their server does not exist.

    Server localhost

        e81b32 /app/public           /
        49cfdf /acme/profiler/public /profiler

Use "puli server add <name> <document-root>" to add a server.

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListMappingsOfNonExistingDefaultServer()
    {
        $this->serverManager->expects($this->any())
            ->method('hasServer')
            ->willReturnMap(array(
                array('localhost', false),
                array('example.com', false),
            ));

        $this->serverManager->expects($this->never())
            ->method('getServer');

        $this->assetManager->expects($this->once())
            ->method('getAssetMappings')
            ->willReturn(array(
                new AssetMapping('/app/public', 'localhost', '/', Uuid::fromString(self::UUID1)),
                new AssetMapping('/acme/blog/public', 'example.com', '/blog', Uuid::fromString(self::UUID2)),
                new AssetMapping('/acme/profiler/public', 'localhost', '/profiler', Uuid::fromString(self::UUID3)),
            ));

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $expected = <<<EOF
The following web assets are disabled since their server does not exist.

    Server localhost

        e81b32 /app/public           /
        49cfdf /acme/profiler/public /profiler

    Server example.com

        33dbec /acme/blog/public /blog

Use "puli server add <name> <document-root>" to add a server.

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEmpty()
    {
        $this->assetManager->expects($this->once())
            ->method('getAssetMappings')
            ->willReturn(array());

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $expected = <<<EOF
No assets are mapped. Use "puli asset map <path> <public-path>" to map assets.

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testAdd()
    {
        $this->assetManager->expects($this->once())
            ->method('addRootAssetMapping')
            ->willReturnCallback(function (AssetMapping $mapping) {
                PHPUnit_Framework_Assert::assertSame('/app/public', $mapping->getGlob());
                PHPUnit_Framework_Assert::assertSame('localhost', $mapping->getServerName());
                PHPUnit_Framework_Assert::assertSame('/', $mapping->getServerPath());
            });

        $args = self::$addCommand->parseArgs(new StringArgs('/app/public localhost'));

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddWithServerPath()
    {
        $this->assetManager->expects($this->once())
            ->method('addRootAssetMapping')
            ->willReturnCallback(function (AssetMapping $mapping) {
                PHPUnit_Framework_Assert::assertSame('/app/public', $mapping->getGlob());
                PHPUnit_Framework_Assert::assertSame('localhost', $mapping->getServerName());
                PHPUnit_Framework_Assert::assertSame('/blog', $mapping->getServerPath());
            });

        $args = self::$addCommand->parseArgs(new StringArgs('/app/public localhost /blog'));

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddForce()
    {
        $this->assetManager->expects($this->once())
            ->method('addRootAssetMapping')
            ->willReturnCallback(function (AssetMapping $mapping, $flags) {
                PHPUnit_Framework_Assert::assertSame('/app/public', $mapping->getGlob());
                PHPUnit_Framework_Assert::assertSame('localhost', $mapping->getServerName());
                PHPUnit_Framework_Assert::assertSame('/', $mapping->getServerPath());
                PHPUnit_Framework_Assert::assertSame(AssetManager::IGNORE_SERVER_NOT_FOUND, $flags);
            });

        $args = self::$addCommand->parseArgs(new StringArgs('--force /app/public localhost'));

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddWithRelativeRepositoryPath()
    {
        $this->assetManager->expects($this->once())
            ->method('addRootAssetMapping')
            ->willReturnCallback(function (AssetMapping $mapping) {
                PHPUnit_Framework_Assert::assertSame('/app/public', $mapping->getGlob());
                PHPUnit_Framework_Assert::assertSame('localhost', $mapping->getServerName());
                PHPUnit_Framework_Assert::assertSame('/', $mapping->getServerPath());
            });

        $args = self::$addCommand->parseArgs(new StringArgs('app/public localhost'));

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddWithRelativeServerPath()
    {
        $this->assetManager->expects($this->once())
            ->method('addRootAssetMapping')
            ->willReturnCallback(function (AssetMapping $mapping) {
                PHPUnit_Framework_Assert::assertSame('/app/public', $mapping->getGlob());
                PHPUnit_Framework_Assert::assertSame('localhost', $mapping->getServerName());
                PHPUnit_Framework_Assert::assertSame('/path', $mapping->getServerPath());
            });

        $args = self::$addCommand->parseArgs(new StringArgs('/app/public localhost path'));

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testUpdateMapping()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('abcd --path /new --server new-server --server-path /new-server'));

        $mapping = new AssetMapping('/app/public', 'localhost', '/');
        $uuid = $mapping->getUuid();

        $this->assetManager->expects($this->once())
            ->method('findAssetMappings')
            ->with(Expr::startsWith('abcd', AssetMapping::UUID))
            ->willReturn(array($mapping));

        $this->assetManager->expects($this->once())
            ->method('addRootAssetMapping')
            ->willReturnCallback(function (AssetMapping $mapping, $flags) use ($uuid) {
                PHPUnit_Framework_Assert::assertSame('/new', $mapping->getGlob());
                PHPUnit_Framework_Assert::assertSame('new-server', $mapping->getServerName());
                PHPUnit_Framework_Assert::assertSame('/new-server', $mapping->getServerPath());
                PHPUnit_Framework_Assert::assertSame($uuid, $mapping->getUuid());
                PHPUnit_Framework_Assert::assertSame(AssetManager::OVERRIDE, $flags);
            });

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateMappingRelativePath()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('abcd --path new'));

        $mapping = new AssetMapping('/app/public', 'localhost', '/');
        $uuid = $mapping->getUuid();

        $this->assetManager->expects($this->once())
            ->method('findAssetMappings')
            ->with(Expr::startsWith('abcd', AssetMapping::UUID))
            ->willReturn(array($mapping));

        $this->assetManager->expects($this->once())
            ->method('addRootAssetMapping')
            ->willReturnCallback(function (AssetMapping $mapping, $flags) use ($uuid) {
                PHPUnit_Framework_Assert::assertSame('/new', $mapping->getGlob());
                PHPUnit_Framework_Assert::assertSame('localhost', $mapping->getServerName());
                PHPUnit_Framework_Assert::assertSame('/', $mapping->getServerPath());
                PHPUnit_Framework_Assert::assertSame($uuid, $mapping->getUuid());
                PHPUnit_Framework_Assert::assertSame(AssetManager::OVERRIDE, $flags);
            });

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateMappingRelativeWebPath()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('abcd --server-path new'));

        $mapping = new AssetMapping('/app/public', 'localhost', '/');
        $uuid = $mapping->getUuid();

        $this->assetManager->expects($this->once())
            ->method('findAssetMappings')
            ->with(Expr::startsWith('abcd', AssetMapping::UUID))
            ->willReturn(array($mapping));

        $this->assetManager->expects($this->once())
            ->method('addRootAssetMapping')
            ->willReturnCallback(function (AssetMapping $mapping, $flags) use ($uuid) {
                PHPUnit_Framework_Assert::assertSame('/app/public', $mapping->getGlob());
                PHPUnit_Framework_Assert::assertSame('localhost', $mapping->getServerName());
                PHPUnit_Framework_Assert::assertSame('/new', $mapping->getServerPath());
                PHPUnit_Framework_Assert::assertSame($uuid, $mapping->getUuid());
                PHPUnit_Framework_Assert::assertSame(AssetManager::OVERRIDE, $flags);
            });

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateMappingForce()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('abcd --path /new --force'));

        $mapping = new AssetMapping('/app/public', 'localhost', '/');
        $uuid = $mapping->getUuid();

        $this->assetManager->expects($this->once())
            ->method('findAssetMappings')
            ->with(Expr::startsWith('abcd', AssetMapping::UUID))
            ->willReturn(array($mapping));

        $this->assetManager->expects($this->once())
            ->method('addRootAssetMapping')
            ->willReturnCallback(function (AssetMapping $mapping, $flags) use ($uuid) {
                PHPUnit_Framework_Assert::assertSame('/new', $mapping->getGlob());
                PHPUnit_Framework_Assert::assertSame('localhost', $mapping->getServerName());
                PHPUnit_Framework_Assert::assertSame('/', $mapping->getServerPath());
                PHPUnit_Framework_Assert::assertSame($uuid, $mapping->getUuid());
                PHPUnit_Framework_Assert::assertSame(AssetManager::OVERRIDE | AssetManager::IGNORE_SERVER_NOT_FOUND, $flags);
            });

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Nothing to update.
     */
    public function testUpdateMappingFailsIfNoChanges()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('abcd'));

        $mapping = new AssetMapping('/app/public', 'localhost', '/');

        $this->assetManager->expects($this->once())
            ->method('findAssetMappings')
            ->with(Expr::startsWith('abcd', AssetMapping::UUID))
            ->willReturn(array($mapping));

        $this->assetManager->expects($this->never())
            ->method('addRootAssetMapping');

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testDeleteMapping()
    {
        $this->assetManager->expects($this->once())
            ->method('findAssetMappings')
            ->with(Expr::startsWith('abcd', AssetMapping::UUID))
            ->willReturn(array(
                $mapping = new AssetMapping('/app/public', 'localhost', '/'),
            ));

        $this->assetManager->expects($this->once())
            ->method('removeRootAssetMapping')
            ->with($mapping->getUuid());

        $args = self::$deleteCommand->parseArgs(new StringArgs('abcd'));

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The mapping with the UUID prefix "abcd" does not exist.
     */
    public function testDeleteMappingFailsIfNotFound()
    {
        $this->assetManager->expects($this->once())
            ->method('findAssetMappings')
            ->with(Expr::startsWith('abcd', AssetMapping::UUID))
            ->willReturn(array());

        $this->assetManager->expects($this->never())
            ->method('removeRootAssetMapping');

        $args = self::$deleteCommand->parseArgs(new StringArgs('abcd'));

        $this->handler->handleDelete($args);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage More than one mapping matches the UUID prefix "abcd".
     */
    public function testDeleteMappingFailsIfAmbiguous()
    {
        $this->assetManager->expects($this->once())
            ->method('findAssetMappings')
            ->with(Expr::startsWith('abcd', AssetMapping::UUID))
            ->willReturn(array(
                new AssetMapping('/app/public1', 'localhost', '/'),
                new AssetMapping('/app/public2', 'localhost', '/'),
            ));

        $this->assetManager->expects($this->never())
            ->method('removeRootAssetMapping');

        $args = self::$deleteCommand->parseArgs(new StringArgs('abcd'));

        $this->handler->handleDelete($args);
    }

    public function testInstall()
    {
        $mapping1 = new AssetMapping('/app/public', 'localhost', '/');
        $mapping2 = new AssetMapping('/acme/blog/public/{css,js}', 'example.com', '/blog');

        $symlinkInstaller = $this->getMock('Puli\Manager\Api\Installer\ResourceInstaller');
        $symlinkInstallerDescriptor = new InstallerDescriptor('symlink', get_class($symlinkInstaller));
        $rsyncInstaller = $this->getMock('Puli\Manager\Api\Installer\ResourceInstaller');
        $rsyncInstallerDescriptor = new InstallerDescriptor('rsync', get_class($rsyncInstaller));

        $localServer = new Server('localhost', 'symlink', 'public_html');
        $remoteServer = new Server('example.com', 'rsync', 'ssh://example.com');

        $resource1 = new GenericResource('/app/public');
        $resource2 = new GenericResource('/acme/blog/public/css');
        $resource3 = new GenericResource('/acme/blog/public/js');

        $params1 = new InstallationParams(
            $symlinkInstaller,
            $symlinkInstallerDescriptor,
            new ArrayResourceCollection(array($resource1)),
            $mapping1,
            $localServer,
            __DIR__
        );
        $params2 = new InstallationParams(
            $rsyncInstaller,
            $rsyncInstallerDescriptor,
            new ArrayResourceCollection(array($resource2, $resource3)),
            $mapping2,
            $remoteServer,
            __DIR__
        );

        $this->assetManager->expects($this->once())
            ->method('getAssetMappings')
            ->willReturn(array($mapping1, $mapping2));

        $this->installationManager->expects($this->at(0))
            ->method('prepareInstallation')
            ->with($mapping1)
            ->willReturn($params1);

        $this->installationManager->expects($this->at(1))
            ->method('prepareInstallation')
            ->with($mapping2)
            ->willReturn($params2);

        $this->installationManager->expects($this->at(2))
            ->method('installResource')
            ->with($resource1, $params1);

        $this->installationManager->expects($this->at(3))
            ->method('installResource')
            ->with($resource2, $params2);

        $this->installationManager->expects($this->at(4))
            ->method('installResource')
            ->with($resource3, $params2);

        $args = self::$installCommand->parseArgs(new StringArgs(''));

        $expected = <<<EOF
Installing /app/public into public_html via symlink...
Installing /acme/blog/public/css into ssh://example.com/blog/css via rsync...
Installing /acme/blog/public/js into ssh://example.com/blog/js via rsync...

EOF;

        $this->assertSame(0, $this->handler->handleInstall($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testInstallWithServer()
    {
        $mapping1 = new AssetMapping('/app/public', 'localhost', '/');
        $mapping2 = new AssetMapping('/acme/blog/public/{css,js}', 'localhost', '/blog');

        $symlinkInstaller = $this->getMock('Puli\Manager\Api\Installer\ResourceInstaller');
        $symlinkInstallerDescriptor = new InstallerDescriptor('symlink', get_class($symlinkInstaller));

        $localServer = new Server('localhost', 'symlink', 'public_html');

        $resource1 = new GenericResource('/app/public');
        $resource2 = new GenericResource('/acme/blog/public/css');
        $resource3 = new GenericResource('/acme/blog/public/js');

        $params1 = new InstallationParams(
            $symlinkInstaller,
            $symlinkInstallerDescriptor,
            new ArrayResourceCollection(array($resource1)),
            $mapping1,
            $localServer,
            __DIR__
        );
        $params2 = new InstallationParams(
            $symlinkInstaller,
            $symlinkInstallerDescriptor,
            new ArrayResourceCollection(array($resource2, $resource3)),
            $mapping2,
            $localServer,
            __DIR__
        );

        $this->assetManager->expects($this->once())
            ->method('findAssetMappings')
            ->with(Expr::same('localhost', AssetMapping::SERVER_NAME))
            ->willReturn(array($mapping1, $mapping2));

        $this->installationManager->expects($this->at(0))
            ->method('prepareInstallation')
            ->with($mapping1)
            ->willReturn($params1);

        $this->installationManager->expects($this->at(1))
            ->method('prepareInstallation')
            ->with($mapping2)
            ->willReturn($params2);

        $this->installationManager->expects($this->at(2))
            ->method('installResource')
            ->with($resource1, $params1);

        $this->installationManager->expects($this->at(3))
            ->method('installResource')
            ->with($resource2, $params2);

        $this->installationManager->expects($this->at(4))
            ->method('installResource')
            ->with($resource3, $params2);

        $args = self::$installCommand->parseArgs(new StringArgs('localhost'));

        $expected = <<<EOF
Installing /app/public into public_html via symlink...
Installing /acme/blog/public/css into public_html/blog/css via symlink...
Installing /acme/blog/public/js into public_html/blog/js via symlink...

EOF;

        $this->assertSame(0, $this->handler->handleInstall($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    /**
     * @expectedException \Puli\Manager\Tests\TestException
     */
    public function testInstallDoesNothingIfPrepareFails()
    {
        $mapping1 = new AssetMapping('/app/public', 'localhost', '/');
        $mapping2 = new AssetMapping('/acme/blog/public/{css,js}', 'localhost', '/blog');

        $symlinkInstaller = $this->getMock('Puli\Manager\Api\Installer\ResourceInstaller');
        $symlinkInstallerDescriptor = new InstallerDescriptor('symlink', get_class($symlinkInstaller));

        $localServer = new Server('localhost', 'symlink', 'public_html');

        $resource1 = new GenericResource('/app/public');

        $params1 = new InstallationParams(
            $symlinkInstaller,
            $symlinkInstallerDescriptor,
            new ArrayResourceCollection(array($resource1)),
            $mapping1,
            $localServer,
            __DIR__
        );

        $this->assetManager->expects($this->once())
            ->method('getAssetMappings')
            ->willReturn(array($mapping1, $mapping2));

        $this->installationManager->expects($this->at(0))
            ->method('prepareInstallation')
            ->with($mapping1)
            ->willReturn($params1);

        $this->installationManager->expects($this->at(1))
            ->method('prepareInstallation')
            ->with($mapping2)
            ->willThrowException(new TestException());

        $this->installationManager->expects($this->never())
            ->method('installResource');

        $args = self::$installCommand->parseArgs(new StringArgs(''));

        $this->handler->handleInstall($args, $this->io);
    }

    public function testInstallNothing()
    {
        $this->assetManager->expects($this->once())
            ->method('getAssetMappings')
            ->willReturn(array());

        $this->installationManager->expects($this->never())
            ->method('prepareInstallation');

        $this->installationManager->expects($this->never())
            ->method('installResource');

        $args = self::$installCommand->parseArgs(new StringArgs(''));

        $expected = <<<EOF
Nothing to install.

EOF;

        $this->assertSame(0, $this->handler->handleInstall($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }
}
