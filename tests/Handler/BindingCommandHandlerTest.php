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

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\Cli\Handler\BindingCommandHandler;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingState;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingCommandHandlerTest extends AbstractCommandHandlerTest
{
    const BINDING_UUID1 = 'bb5a07d6-e979-4c35-9883-4d8c1165e3d5';
    const BINDING_UUID2 = 'cc9f2259-8587-4d4e-9dda-f0ff87b4e871';
    const BINDING_UUID3 = '9ac78a31-12ef-4166-93fd-1470c5e34622';
    const BINDING_UUID4 = '3cf75784-c86f-4b2c-bc91-aeba0b1a77af';
    const BINDING_UUID5 = 'd0e9806c-092b-4641-a6b5-a414c00dc552';
    const BINDING_UUID6 = 'd0670743-47c8-4493-a457-74c049aabc0a';
    const BINDING_UUID7 = '970abaae-e251-4cc0-81eb-32722628246d';
    const BINDING_UUID8 = 'a0b6c7d2-107a-496a-abfd-8b77ba298719';
    const BINDING_UUID9 = 'e33d03bd-bb21-4e97-99d2-c33f679ce61d';
    const BINDING_UUID10 = '19b06961-e54b-4c4b-bfee-fa2f408a283f';
    const BINDING_UUID11 = 'dd7458ff-8d76-4033-bb58-f366b565958f';
    const BINDING_UUID12 = 'ddb6554a-6a1d-4bb2-82de-97fa5ccdc497';
    const BINDING_UUID13 = '424d6853-e381-46d4-b110-668cb16c3279';
    const BINDING_UUID14 = '516159c4-e90a-44c8-b781-9d10b9f201ef';
    const BINDING_UUID15 = 'b7b2c3ee-b8fa-4d76-827c-2879033aa28f';
    const BINDING_UUID16 = '24213cf3-d609-4c00-b73d-deadc3098593';
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
    private static $removeCommand;

    /**
     * @var Command
     */
    private static $enableCommand;

    /**
     * @var Command
     */
    private static $disableCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var BindingCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$listCommand = self::$application->getCommand('binding')->getSubCommand('list');
        self::$saveCommand = self::$application->getCommand('binding')->getSubCommand('add');
        self::$removeCommand = self::$application->getCommand('binding')->getSubCommand('remove');
        self::$enableCommand = self::$application->getCommand('binding')->getSubCommand('enable');
        self::$disableCommand = self::$application->getCommand('binding')->getSubCommand('disable');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->discoveryManager = $this->getMock('Puli\Manager\Api\Discovery\DiscoveryManager');
        $this->packages = new PackageCollection(array(
            new RootPackage(new RootPackageFile('vendor/root'), '/root'),
            new Package(new PackageFile('vendor/package1'), '/package1'),
            new Package(new PackageFile('vendor/package2'), '/package2'),
        ));
        $this->handler = new BindingCommandHandler($this->discoveryManager, $this->packages);
    }

    public function testListAllBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    vendor/root
    bb5a07 /root/enabled my/type
    cc9f22 /overridden   my/type

    vendor/package1
    970aba /package1/enabled my/type

    vendor/package2
    ddb655 /package2/enabled my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    vendor/root
    9ac78a /root/disabled my/type

    vendor/package1
    a0b6c7 /package1/disabled my/type

    vendor/package2
    424d68 /package2/disabled my/type

Bindings that are neither enabled nor disabled:
 (use "puli bind --enable <uuid>" to enable)

    vendor/root
    3cf757 /root/undecided my/type

    vendor/package1
    e33d03 /package1/undecided my/type

    vendor/package2
    516159 /package2/undecided my/type

The types of the following bindings are not loaded:
 (install or fix their type definitions to enable)

    vendor/root
    d0e980 /root/type-not-loaded my/type

    vendor/package1
    19b069 /package1/type-not-loaded my/type

    vendor/package2
    b7b2c3 /package2/type-not-loaded my/type

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    vendor/root
    d06707 /root/invalid my/type

    vendor/package1
    dd7458 /package1/invalid my/type

    vendor/package2
    24213c /package2/invalid my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListRootBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--root'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    bb5a07 /root/enabled my/type
    cc9f22 /overridden   my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    9ac78a /root/disabled my/type

Bindings that are neither enabled nor disabled:
 (use "puli bind --enable <uuid>" to enable)

    3cf757 /root/undecided my/type

The types of the following bindings are not loaded:
 (install or fix their type definitions to enable)

    d0e980 /root/type-not-loaded my/type

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    d06707 /root/invalid my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListPackageBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--package=vendor/package1'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    970aba /package1/enabled my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    a0b6c7 /package1/disabled my/type

Bindings that are neither enabled nor disabled:
 (use "puli bind --enable <uuid>" to enable)

    e33d03 /package1/undecided my/type

The types of the following bindings are not loaded:
 (install or fix their type definitions to enable)

    19b069 /package1/type-not-loaded my/type

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    dd7458 /package1/invalid my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListRootAndPackageBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--root --package=vendor/package1'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    vendor/root
    bb5a07 /root/enabled my/type
    cc9f22 /overridden   my/type

    vendor/package1
    970aba /package1/enabled my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    vendor/root
    9ac78a /root/disabled my/type

    vendor/package1
    a0b6c7 /package1/disabled my/type

Bindings that are neither enabled nor disabled:
 (use "puli bind --enable <uuid>" to enable)

    vendor/root
    3cf757 /root/undecided my/type

    vendor/package1
    e33d03 /package1/undecided my/type

The types of the following bindings are not loaded:
 (install or fix their type definitions to enable)

    vendor/root
    d0e980 /root/type-not-loaded my/type

    vendor/package1
    19b069 /package1/type-not-loaded my/type

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    vendor/root
    d06707 /root/invalid my/type

    vendor/package1
    dd7458 /package1/invalid my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListMultiplePackageBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--package=vendor/package1 --package=vendor/package2'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    vendor/package1
    970aba /package1/enabled my/type

    vendor/package2
    ddb655 /package2/enabled my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    vendor/package1
    a0b6c7 /package1/disabled my/type

    vendor/package2
    424d68 /package2/disabled my/type

Bindings that are neither enabled nor disabled:
 (use "puli bind --enable <uuid>" to enable)

    vendor/package1
    e33d03 /package1/undecided my/type

    vendor/package2
    516159 /package2/undecided my/type

The types of the following bindings are not loaded:
 (install or fix their type definitions to enable)

    vendor/package1
    19b069 /package1/type-not-loaded my/type

    vendor/package2
    b7b2c3 /package2/type-not-loaded my/type

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    vendor/package1
    dd7458 /package1/invalid my/type

    vendor/package2
    24213c /package2/invalid my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root
bb5a07 /root/enabled my/type
cc9f22 /overridden   my/type

vendor/package1
970aba /package1/enabled my/type

vendor/package2
ddb655 /package2/enabled my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListDisabledBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--disabled'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root
9ac78a /root/disabled my/type

vendor/package1
a0b6c7 /package1/disabled my/type

vendor/package2
424d68 /package2/disabled my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListUndecidedBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--undecided'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root
3cf757 /root/undecided my/type

vendor/package1
e33d03 /package1/undecided my/type

vendor/package2
516159 /package2/undecided my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListBindingsWithTypeNotLoaded()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--type-not-loaded'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root
d0e980 /root/type-not-loaded my/type

vendor/package1
19b069 /package1/type-not-loaded my/type

vendor/package2
b7b2c3 /package2/type-not-loaded my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListInvalidBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--invalid'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root
d06707 /root/invalid my/type

vendor/package1
dd7458 /package1/invalid my/type

vendor/package2
24213c /package2/invalid my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }


    public function testListEnabledAndDisabledBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --disabled'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    vendor/root
    bb5a07 /root/enabled my/type
    cc9f22 /overridden   my/type

    vendor/package1
    970aba /package1/enabled my/type

    vendor/package2
    ddb655 /package2/enabled my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    vendor/root
    9ac78a /root/disabled my/type

    vendor/package1
    a0b6c7 /package1/disabled my/type

    vendor/package2
    424d68 /package2/disabled my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledBindingsFromRoot()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --root'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
bb5a07 /root/enabled my/type
cc9f22 /overridden   my/type

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledBindingsFromPackage()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --package=vendor/package2'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
ddb655 /package2/enabled my/type

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListBindingsWithParameters()
    {
        $this->discoveryManager->expects($this->any())
            ->method('findBindings')
            ->willReturnCallback($this->returnFromMap(array(
                array($this->packageAndState('vendor/root', BindingState::ENABLED), array(
                    new BindingDescriptor('/path', 'my/type', array(
                        'param1' => 'value1',
                        'param2' => 'value2',
                    ), 'glob', Uuid::fromString(self::BINDING_UUID1)),
                )),
            )));

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $statusCode = $this->handler->handleList($args, $this->io);

        $nbsp = "\xc2\xa0";
        $expected = <<<EOF
Enabled bindings:

    vendor/root
    bb5a07 /path my/type (param1="value1",{$nbsp}param2="value2")


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testSaveBindingWithRelativePath()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('path my/type'));

        $this->discoveryManager->expects($this->once())
            ->method('addBinding')
            ->willReturnCallback(function (BindingDescriptor $bindingDescriptor) {
                PHPUnit_Framework_Assert::assertSame('/path', $bindingDescriptor->getQuery());
                PHPUnit_Framework_Assert::assertSame('my/type', $bindingDescriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $bindingDescriptor->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('glob', $bindingDescriptor->getLanguage());
            });

        $statusCode = $this->handler->handleSave($args, $this->io);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testSaveBindingWithAbsolutePath()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('/path my/type'));

        $this->discoveryManager->expects($this->once())
            ->method('addBinding')
            ->willReturnCallback(function (BindingDescriptor $bindingDescriptor) {
                PHPUnit_Framework_Assert::assertSame('/path', $bindingDescriptor->getQuery());
                PHPUnit_Framework_Assert::assertSame('my/type', $bindingDescriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $bindingDescriptor->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('glob', $bindingDescriptor->getLanguage());
            });

        $statusCode = $this->handler->handleSave($args, $this->io);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testSaveBindingWithLanguage()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('/path my/type --language lang'));

        $this->discoveryManager->expects($this->once())
            ->method('addBinding')
            ->willReturnCallback(function (BindingDescriptor $bindingDescriptor) {
                PHPUnit_Framework_Assert::assertSame('/path', $bindingDescriptor->getQuery());
                PHPUnit_Framework_Assert::assertSame('my/type', $bindingDescriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $bindingDescriptor->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('lang', $bindingDescriptor->getLanguage());
            });

        $statusCode = $this->handler->handleSave($args, $this->io);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testSaveBindingWithParameters()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('/path my/type --param key1=value --param key2=true'));

        $this->discoveryManager->expects($this->once())
            ->method('addBinding')
            ->willReturnCallback(function (BindingDescriptor $bindingDescriptor) {
                PHPUnit_Framework_Assert::assertSame('/path', $bindingDescriptor->getQuery());
                PHPUnit_Framework_Assert::assertSame('my/type', $bindingDescriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(
                    'key1' => 'value',
                    'key2' => true,
                ), $bindingDescriptor->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('glob', $bindingDescriptor->getLanguage());
            });

        $statusCode = $this->handler->handleSave($args, $this->io);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The "--param" option expects a parameter in the form "key=value". Got: "key1"
     */
    public function testSaveFailsIfInvalidParameter()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('/path my/type --param key1'));

        $this->discoveryManager->expects($this->never())
            ->method('addBinding');

        $this->handler->handleSave($args, $this->io);
    }

    public function testSaveBindingForce()
    {
        $args = self::$saveCommand->parseArgs(new StringArgs('--force path my/type'));

        $this->discoveryManager->expects($this->once())
            ->method('addBinding')
            ->willReturnCallback(function (BindingDescriptor $bindingDescriptor, $flags) {
                PHPUnit_Framework_Assert::assertSame('/path', $bindingDescriptor->getQuery());
                PHPUnit_Framework_Assert::assertSame('my/type', $bindingDescriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $bindingDescriptor->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('glob', $bindingDescriptor->getLanguage());
                PHPUnit_Framework_Assert::assertSame(DiscoveryManager::NO_TYPE_CHECK, $flags);
            });

        $statusCode = $this->handler->handleSave($args, $this->io);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testRemoveBinding()
    {
        $args = self::$removeCommand->parseArgs(new StringArgs('ab12'));
        $descriptor = new BindingDescriptor('/path', 'my/type', array(), 'glob');

        $this->discoveryManager->expects($this->once())
            ->method('findBindings')
            ->willReturnCallback($this->returnForExpr(
                $this->packageAndUuid('vendor/root', 'ab12'),
                array($descriptor)
            ));

        $this->discoveryManager->expects($this->once())
            ->method('removeBinding')
            ->with($descriptor->getUuid());

        $statusCode = $this->handler->handleRemove($args, $this->io);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage More than one binding
     */
    public function testRemoveBindingFailsIfAmbiguous()
    {
        $args = self::$removeCommand->parseArgs(new StringArgs('ab12'));

        $this->discoveryManager->expects($this->once())
            ->method('findBindings')
            ->willReturnCallback($this->returnForExpr(
                $this->packageAndUuid('vendor/root', 'ab12'),
                array(
                    new BindingDescriptor('/path1', 'my/type', array(), 'glob'),
                    new BindingDescriptor('/path2', 'my/type', array(), 'glob'),
                )
            ));

        $this->discoveryManager->expects($this->never())
            ->method('removeBinding');

        $this->handler->handleRemove($args, $this->io);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The binding "ab12" does not exist
     */
    public function testRemoveBindingFailsIfNotFound()
    {
        $args = self::$removeCommand->parseArgs(new StringArgs('ab12'));

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindings')
            ->willReturnCallback($this->returnForExpr(
                $this->packageAndUuid('vendor/root', 'ab12'),
                array()
            ));

        $this->discoveryManager->expects($this->at(1))
            ->method('findBindings')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array()
            ));

        $this->discoveryManager->expects($this->never())
            ->method('removeBinding');

        $this->handler->handleRemove($args, $this->io);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Can only delete bindings from the root package
     */
    public function testRemoveBindingFailsIfNoRootBinding()
    {
        $args = self::$removeCommand->parseArgs(new StringArgs('ab12'));

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindings')
            ->willReturnCallback($this->returnForExpr(
                $this->packageAndUuid('vendor/root', 'ab12'),
                array()
            ));

        $this->discoveryManager->expects($this->at(1))
            ->method('findBindings')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array(new BindingDescriptor('/path1', 'my/type', array(), 'glob'))
            ));

        $this->discoveryManager->expects($this->never())
            ->method('removeBinding');

        $this->handler->handleRemove($args, $this->io);
    }

    public function testEnableBindings()
    {
        $args = self::$enableCommand->parseArgs(new StringArgs('ab12'));
        $descriptor1 = new BindingDescriptor('/path', 'my/type', array(), 'glob');
        $descriptor2 = new BindingDescriptor('/path', 'my/type', array(), 'glob');

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindings')
            ->with($this->uuid('ab12'))
            ->willReturn(array($descriptor1, $descriptor2));

        $this->discoveryManager->expects($this->at(1))
            ->method('enableBinding')
            ->with($descriptor1->getUuid());

        $this->discoveryManager->expects($this->at(2))
            ->method('enableBinding')
            ->with($descriptor2->getUuid());

        $statusCode = $this->handler->handleEnable($args, $this->io);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The binding "ab12" does not exist
     */
    public function testEnableBindingsFailsIfNotFound()
    {
        $args = self::$enableCommand->parseArgs(new StringArgs('ab12'));

        $this->discoveryManager->expects($this->once())
            ->method('findBindings')
            ->with($this->uuid('ab12'))
            ->willReturn(array());

        $this->discoveryManager->expects($this->never())
            ->method('enableBinding');

        $this->handler->handleEnable($args, $this->io);
    }

    public function testDisableBindings()
    {
        $args = self::$disableCommand->parseArgs(new StringArgs('ab12'));
        $descriptor1 = new BindingDescriptor('/path', 'my/type', array(), 'glob');
        $descriptor2 = new BindingDescriptor('/path', 'my/type', array(), 'glob');

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindings')
            ->with($this->uuid('ab12'))
            ->willReturn(array($descriptor1, $descriptor2));

        $this->discoveryManager->expects($this->at(1))
            ->method('disableBinding')
            ->with($descriptor1->getUuid());

        $this->discoveryManager->expects($this->at(2))
            ->method('disableBinding')
            ->with($descriptor2->getUuid());

        $statusCode = $this->handler->handleDisable($args, $this->io);

        $this->assertSame(0, $statusCode);
        $this->assertEmpty($this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The binding "ab12" does not exist
     */
    public function testDisableBindingsFailsIfNotFound()
    {
        $args = self::$disableCommand->parseArgs(new StringArgs('ab12'));

        $this->discoveryManager->expects($this->once())
            ->method('findBindings')
            ->with($this->uuid('ab12'))
            ->willReturn(array());

        $this->discoveryManager->expects($this->never())
            ->method('disableBinding');

        $this->handler->handleDisable($args, $this->io);
    }

    private function initDefaultBindings()
    {
        $this->discoveryManager->expects($this->any())
            ->method('findBindings')
            ->willReturnCallback($this->returnFromMap(array(
                array($this->packageAndState('vendor/root', BindingState::ENABLED), array(
                    new BindingDescriptor('/root/enabled', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID1)),
                    new BindingDescriptor('/overridden', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID2)),
                )),
                array($this->packageAndState('vendor/root', BindingState::DISABLED), array(
                    new BindingDescriptor('/root/disabled', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID3)),
                )),
                array($this->packageAndState('vendor/root', BindingState::UNDECIDED), array(
                    new BindingDescriptor('/root/undecided', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID4)),
                )),
                array($this->packageAndState('vendor/root', BindingState::TYPE_NOT_LOADED), array(
                    new BindingDescriptor('/root/type-not-loaded', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID5)),
                )),
                array($this->packageAndState('vendor/root', BindingState::INVALID), array(
                    new BindingDescriptor('/root/invalid', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID6)),
                )),
                array($this->packageAndState('vendor/package1', BindingState::ENABLED), array(
                    new BindingDescriptor('/package1/enabled', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID7)),
                )),
                array($this->packageAndState('vendor/package1', BindingState::DISABLED), array(
                    new BindingDescriptor('/package1/disabled', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID8)),
                )),
                array($this->packageAndState('vendor/package1', BindingState::UNDECIDED), array(
                    new BindingDescriptor('/package1/undecided', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID9)),
                )),
                array($this->packageAndState('vendor/package1', BindingState::TYPE_NOT_LOADED), array(
                    new BindingDescriptor('/package1/type-not-loaded', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID10)),
                )),
                array($this->packageAndState('vendor/package1', BindingState::INVALID), array(
                    new BindingDescriptor('/package1/invalid', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID11)),
                )),
                array($this->packageAndState('vendor/package2', BindingState::ENABLED), array(
                    new BindingDescriptor('/package2/enabled', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID12)),
                )),
                array($this->packageAndState('vendor/package2', BindingState::DISABLED), array(
                    new BindingDescriptor('/package2/disabled', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID13)),
                )),
                array($this->packageAndState('vendor/package2', BindingState::UNDECIDED), array(
                    new BindingDescriptor('/package2/undecided', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID14)),
                )),
                array($this->packageAndState('vendor/package2', BindingState::TYPE_NOT_LOADED), array(
                    new BindingDescriptor('/package2/type-not-loaded', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID15)),
                )),
                array($this->packageAndState('vendor/package2', BindingState::INVALID), array(
                    new BindingDescriptor('/package2/invalid', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID16)),
                )),
            )));
    }

    private function packageAndState($packageName, $state)
    {
        return Expr::same(BindingDescriptor::CONTAINING_PACKAGE, $packageName)
            ->andSame(BindingDescriptor::STATE, $state);
    }

    private function packageAndUuid($packageName, $uuid)
    {
        return Expr::same(BindingDescriptor::CONTAINING_PACKAGE, $packageName)
            ->andStartsWith(BindingDescriptor::UUID, $uuid);
    }

    private function uuid($uuid)
    {
        return Expr::startsWith(BindingDescriptor::UUID, $uuid);
    }

    private function returnForExpr(Expression $expr, $result)
    {
        // This method is needed since PHPUnit's ->with() method does not
        // internally clone the passed argument. Since we call the same method
        // findBindings() twice with the *same* object, but modify the state of
        // that object in between, PHPUnit fails since the state of the object
        // *after* the test does not match the first assertion anymore
        return function (Expression $actualExpr) use ($expr, $result) {
            PHPUnit_Framework_Assert::assertTrue($actualExpr->equals($expr));

            return $result;
        };
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
