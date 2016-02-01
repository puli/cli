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
use Puli\Cli\Handler\BindCommandHandler;
use Puli\Cli\Tests\Fixtures\Bar;
use Puli\Cli\Tests\Fixtures\Foo;
use Puli\Discovery\Binding\ClassBinding;
use Puli\Discovery\Binding\ResourceBinding;
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
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindCommandHandlerTest extends AbstractCommandHandlerTest
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
    const BINDING_UUID17 = '47491d2e-8d20-4a61-947a-6448533146d2';
    const BINDING_UUID18 = '7d26ae02-a3bb-4399-8829-95cccd20ceb7';
    const BINDING_UUID19 = '53e67ca0-df93-4022-a9c8-1be4e012139b';

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
     * @var BindCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$listCommand = self::$application->getCommand('bind')->getSubCommand('list');
        self::$addCommand = self::$application->getCommand('bind')->getSubCommand('add');
        self::$updateCommand = self::$application->getCommand('bind')->getSubCommand('update');
        self::$deleteCommand = self::$application->getCommand('bind')->getSubCommand('delete');
        self::$enableCommand = self::$application->getCommand('bind')->getSubCommand('enable');
        self::$disableCommand = self::$application->getCommand('bind')->getSubCommand('disable');
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
        $this->handler = new BindCommandHandler($this->discoveryManager, $this->packages);
    }

    public function testListAllBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $expected = <<<EOF
The following bindings are currently enabled:

    Package: vendor/root

        UUID    Glob                    Type
        bb5a07  /root/enabled           Foo
        cc9f22  /overridden             Foo
        47491d  BindCommandHandlerTest  Bar

    Package: vendor/package1

        UUID    Glob               Type
        970aba  /package1/enabled  Foo

    Package: vendor/package2

        UUID    Glob               Type
        ddb655  /package2/enabled  Foo

The following bindings are disabled:
 (use "puli bind --enable <uuid>" to enable)

    Package: vendor/root

        UUID    Glob            Type
        9ac78a  /root/disabled  Foo

    Package: vendor/package1

        UUID    Glob                Type
        a0b6c7  /package1/disabled  Foo

    Package: vendor/package2

        UUID    Glob                Type
        424d68  /package2/disabled  Foo

The types of the following bindings could not be found:
 (install or create their type definitions to enable)

    Package: vendor/root

        UUID    Glob                  Type
        d0e980  /root/type-not-found  Foo

    Package: vendor/package1

        UUID    Glob                      Type
        19b069  /package1/type-not-found  Foo

    Package: vendor/package2

        UUID    Glob                      Type
        b7b2c3  /package2/type-not-found  Foo

The types of the following bindings are not enabled:
 (remove the duplicate type definitions to enable)

    Package: vendor/root

        UUID    Glob                    Type
        47491d  /root/type-not-enabled  Foo

    Package: vendor/package1

        UUID    Glob                       Type
        7d26ae  /package1/type-not-enable  Foo

    Package: vendor/package2

        UUID    Glob                        Type
        53e67c  /package2/type-not-enabled  Foo

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    Package: vendor/root

        UUID    Glob           Type
        d06707  /root/invalid  Foo

    Package: vendor/package1

        UUID    Glob               Type
        dd7458  /package1/invalid  Foo

    Package: vendor/package2

        UUID    Glob               Type
        24213c  /package2/invalid  Foo


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListRootBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--root'));

        $expected = <<<EOF
The following bindings are currently enabled:

    UUID    Glob                    Type
    bb5a07  /root/enabled           Foo
    cc9f22  /overridden             Foo
    47491d  BindCommandHandlerTest  Bar

The following bindings are disabled:
 (use "puli bind --enable <uuid>" to enable)

    UUID    Glob            Type
    9ac78a  /root/disabled  Foo

The types of the following bindings could not be found:
 (install or create their type definitions to enable)

    UUID    Glob                  Type
    d0e980  /root/type-not-found  Foo

The types of the following bindings are not enabled:
 (remove the duplicate type definitions to enable)

    UUID    Glob                    Type
    47491d  /root/type-not-enabled  Foo

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    UUID    Glob           Type
    d06707  /root/invalid  Foo


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListPackageBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--package=vendor/package1'));

        $expected = <<<EOF
The following bindings are currently enabled:

    UUID    Glob               Type
    970aba  /package1/enabled  Foo

The following bindings are disabled:
 (use "puli bind --enable <uuid>" to enable)

    UUID    Glob                Type
    a0b6c7  /package1/disabled  Foo

The types of the following bindings could not be found:
 (install or create their type definitions to enable)

    UUID    Glob                      Type
    19b069  /package1/type-not-found  Foo

The types of the following bindings are not enabled:
 (remove the duplicate type definitions to enable)

    UUID    Glob                       Type
    7d26ae  /package1/type-not-enable  Foo

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    UUID    Glob               Type
    dd7458  /package1/invalid  Foo


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListRootAndPackageBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--root --package=vendor/package1'));

        $expected = <<<EOF
The following bindings are currently enabled:

    Package: vendor/root

        UUID    Glob                    Type
        bb5a07  /root/enabled           Foo
        cc9f22  /overridden             Foo
        47491d  BindCommandHandlerTest  Bar

    Package: vendor/package1

        UUID    Glob               Type
        970aba  /package1/enabled  Foo

The following bindings are disabled:
 (use "puli bind --enable <uuid>" to enable)

    Package: vendor/root

        UUID    Glob            Type
        9ac78a  /root/disabled  Foo

    Package: vendor/package1

        UUID    Glob                Type
        a0b6c7  /package1/disabled  Foo

The types of the following bindings could not be found:
 (install or create their type definitions to enable)

    Package: vendor/root

        UUID    Glob                  Type
        d0e980  /root/type-not-found  Foo

    Package: vendor/package1

        UUID    Glob                      Type
        19b069  /package1/type-not-found  Foo

The types of the following bindings are not enabled:
 (remove the duplicate type definitions to enable)

    Package: vendor/root

        UUID    Glob                    Type
        47491d  /root/type-not-enabled  Foo

    Package: vendor/package1

        UUID    Glob                       Type
        7d26ae  /package1/type-not-enable  Foo

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    Package: vendor/root

        UUID    Glob           Type
        d06707  /root/invalid  Foo

    Package: vendor/package1

        UUID    Glob               Type
        dd7458  /package1/invalid  Foo


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListMultiplePackageBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--package=vendor/package1 --package=vendor/package2'));

        $expected = <<<EOF
The following bindings are currently enabled:

    Package: vendor/package1

        UUID    Glob               Type
        970aba  /package1/enabled  Foo

    Package: vendor/package2

        UUID    Glob               Type
        ddb655  /package2/enabled  Foo

The following bindings are disabled:
 (use "puli bind --enable <uuid>" to enable)

    Package: vendor/package1

        UUID    Glob                Type
        a0b6c7  /package1/disabled  Foo

    Package: vendor/package2

        UUID    Glob                Type
        424d68  /package2/disabled  Foo

The types of the following bindings could not be found:
 (install or create their type definitions to enable)

    Package: vendor/package1

        UUID    Glob                      Type
        19b069  /package1/type-not-found  Foo

    Package: vendor/package2

        UUID    Glob                      Type
        b7b2c3  /package2/type-not-found  Foo

The types of the following bindings are not enabled:
 (remove the duplicate type definitions to enable)

    Package: vendor/package1

        UUID    Glob                       Type
        7d26ae  /package1/type-not-enable  Foo

    Package: vendor/package2

        UUID    Glob                        Type
        53e67c  /package2/type-not-enabled  Foo

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    Package: vendor/package1

        UUID    Glob               Type
        dd7458  /package1/invalid  Foo

    Package: vendor/package2

        UUID    Glob               Type
        24213c  /package2/invalid  Foo


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled'));

        $expected = <<<EOF
Package: vendor/root

    UUID    Glob                    Type
    bb5a07  /root/enabled           Foo
    cc9f22  /overridden             Foo
    47491d  BindCommandHandlerTest  Bar

Package: vendor/package1

    UUID    Glob               Type
    970aba  /package1/enabled  Foo

Package: vendor/package2

    UUID    Glob               Type
    ddb655  /package2/enabled  Foo


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListDisabledBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--disabled'));

        $expected = <<<EOF
Package: vendor/root

    UUID    Glob            Type
    9ac78a  /root/disabled  Foo

Package: vendor/package1

    UUID    Glob                Type
    a0b6c7  /package1/disabled  Foo

Package: vendor/package2

    UUID    Glob                Type
    424d68  /package2/disabled  Foo


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListBindingsWithTypeNotFound()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--type-not-found'));

        $expected = <<<EOF
Package: vendor/root

    UUID    Glob                  Type
    d0e980  /root/type-not-found  Foo

Package: vendor/package1

    UUID    Glob                      Type
    19b069  /package1/type-not-found  Foo

Package: vendor/package2

    UUID    Glob                      Type
    b7b2c3  /package2/type-not-found  Foo


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListBindingsWithTypeNotEnabled()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--type-not-enabled'));

        $expected = <<<EOF
Package: vendor/root

    UUID    Glob                    Type
    47491d  /root/type-not-enabled  Foo

Package: vendor/package1

    UUID    Glob                       Type
    7d26ae  /package1/type-not-enable  Foo

Package: vendor/package2

    UUID    Glob                        Type
    53e67c  /package2/type-not-enabled  Foo


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListInvalidBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--invalid'));

        $expected = <<<EOF
Package: vendor/root

    UUID    Glob           Type
    d06707  /root/invalid  Foo

Package: vendor/package1

    UUID    Glob               Type
    dd7458  /package1/invalid  Foo

Package: vendor/package2

    UUID    Glob               Type
    24213c  /package2/invalid  Foo


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledAndDisabledBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --disabled'));

        $expected = <<<EOF
The following bindings are currently enabled:

    Package: vendor/root

        UUID    Glob                    Type
        bb5a07  /root/enabled           Foo
        cc9f22  /overridden             Foo
        47491d  BindCommandHandlerTest  Bar

    Package: vendor/package1

        UUID    Glob               Type
        970aba  /package1/enabled  Foo

    Package: vendor/package2

        UUID    Glob               Type
        ddb655  /package2/enabled  Foo

The following bindings are disabled:
 (use "puli bind --enable <uuid>" to enable)

    Package: vendor/root

        UUID    Glob            Type
        9ac78a  /root/disabled  Foo

    Package: vendor/package1

        UUID    Glob                Type
        a0b6c7  /package1/disabled  Foo

    Package: vendor/package2

        UUID    Glob                Type
        424d68  /package2/disabled  Foo


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledBindingsFromRoot()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --root'));

        $expected = <<<EOF
UUID    Glob                    Type
bb5a07  /root/enabled           Foo
cc9f22  /overridden             Foo
47491d  BindCommandHandlerTest  Bar

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledBindingsFromPackage()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --package=vendor/package2'));

        $expected = <<<EOF
UUID    Glob               Type
ddb655  /package2/enabled  Foo

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListBindingsWithParameters()
    {
        $this->discoveryManager->expects($this->any())
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnFromMap(array(
                array($this->packageAndState('vendor/root', BindingState::ENABLED), array(
                    new BindingDescriptor(new ResourceBinding('/path', Foo::clazz, array(
                        'param1' => 'value1',
                        'param2' => 'value2',
                    ), 'glob', Uuid::fromString(self::BINDING_UUID1))),
                )),
            )));

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $nbsp = "\xc2\xa0";
        $expected = <<<EOF
The following bindings are currently enabled:

    Package: vendor/root

        UUID    Glob   Type
        bb5a07  /path  Foo (param1="value1",{$nbsp}param2="value2")


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNoBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
No resource bindings. Use "puli bind <artifact> <type>" to bind a resource to a type.

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testAddResourceBindingWithRelativePath()
    {
        $args = self::$addCommand->parseArgs(new StringArgs('path Puli\Cli\Tests\Fixtures\Foo'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ResourceBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame('/path', $descriptor->getBinding()->getQuery());
                PHPUnit_Framework_Assert::assertSame(Foo::clazz, $descriptor->getBinding()->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $descriptor->getBinding()->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('glob', $descriptor->getBinding()->getLanguage());
            });

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddResourceBindingWithAbsolutePath()
    {
        $args = self::$addCommand->parseArgs(new StringArgs('/path Puli\Cli\Tests\Fixtures\Foo'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ResourceBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame('/path', $descriptor->getBinding()->getQuery());
                PHPUnit_Framework_Assert::assertSame(Foo::clazz, $descriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $descriptor->getBinding()->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('glob', $descriptor->getBinding()->getLanguage());
            });

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddResourceBindingWithLanguage()
    {
        $args = self::$addCommand->parseArgs(new StringArgs('/path Puli\Cli\Tests\Fixtures\Foo --language lang'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ResourceBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame('/path', $descriptor->getBinding()->getQuery());
                PHPUnit_Framework_Assert::assertSame(Foo::clazz, $descriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $descriptor->getBinding()->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('lang', $descriptor->getBinding()->getLanguage());
            });

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddClassBinding()
    {
        $args = self::$addCommand->parseArgs(new StringArgs('Puli\Cli\Tests\Handler\BindCommandHandlerTest Puli\Cli\Tests\Fixtures\Foo'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ClassBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame('Puli\Cli\Tests\Handler\BindCommandHandlerTest', $descriptor->getBinding()->getClassName());
                PHPUnit_Framework_Assert::assertSame(Foo::clazz, $descriptor->getBinding()->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $descriptor->getBinding()->getParameterValues());
            });

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddClassBindingWithoutBackSlash()
    {
        $args = self::$addCommand->parseArgs(new StringArgs('--class BindCommandHandlerTest Puli\Cli\Tests\Fixtures\Foo'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ClassBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame('BindCommandHandlerTest', $descriptor->getBinding()->getClassName());
                PHPUnit_Framework_Assert::assertSame(Foo::clazz, $descriptor->getBinding()->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $descriptor->getBinding()->getParameterValues());
            });

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testAddBindingWithParameters()
    {
        $args = self::$addCommand->parseArgs(new StringArgs('/path Puli\Cli\Tests\Fixtures\Foo --param key1=value --param key2=true'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ResourceBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame('/path', $descriptor->getBinding()->getQuery());
                PHPUnit_Framework_Assert::assertSame(Foo::clazz, $descriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(
                    'key1' => 'value',
                    'key2' => true,
                ), $descriptor->getBinding()->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('glob', $descriptor->getBinding()->getLanguage());
            });

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The "--param" option expects a parameter in the form "key=value". Got: "key1"
     */
    public function testAddFailsIfInvalidParameter()
    {
        $args = self::$addCommand->parseArgs(new StringArgs('/path Puli\Cli\Tests\Fixtures\Foo --param key1'));

        $this->discoveryManager->expects($this->never())
            ->method('addRootBindingDescriptor');

        $this->handler->handleAdd($args);
    }

    public function testAddBindingForce()
    {
        $args = self::$addCommand->parseArgs(new StringArgs('--force path Puli\Cli\Tests\Fixtures\Foo'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor, $flags) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ResourceBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame('/path', $descriptor->getBinding()->getQuery());
                PHPUnit_Framework_Assert::assertSame(Foo::clazz, $descriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $descriptor->getBinding()->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('glob', $descriptor->getBinding()->getLanguage());
                PHPUnit_Framework_Assert::assertSame(DiscoveryManager::OVERRIDE | DiscoveryManager::IGNORE_TYPE_NOT_FOUND | DiscoveryManager::IGNORE_TYPE_NOT_ENABLED, $flags);
            });

        $this->assertSame(0, $this->handler->handleAdd($args));
    }

    public function testUpdateResourceBinding()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('ab12 --query /new --language xpath --type my/other --param param2=new2'));
        $binding = new ResourceBinding('/old', Foo::clazz, array(
            'param1' => 'value1',
            'param2' => 'value2',
        ), 'glob');
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->packages->getRootPackage());
        $uuid = $descriptor->getUuid();

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array($descriptor)
            ));

        $this->discoveryManager->expects($this->at(1))
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor, $flags) use ($uuid) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ResourceBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame($uuid, $descriptor->getUuid());
                PHPUnit_Framework_Assert::assertSame('/new', $descriptor->getBinding()->getQuery());
                PHPUnit_Framework_Assert::assertSame('my/other', $descriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(
                    'param1' => 'value1',
                    'param2' => 'new2',
                ), $descriptor->getBinding()->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('xpath', $descriptor->getBinding()->getLanguage());
                PHPUnit_Framework_Assert::assertSame(DiscoveryManager::OVERRIDE, $flags);
            });

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateResourceBindingWithRelativePath()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('ab12 --query new'));
        $descriptor = new BindingDescriptor(new ResourceBinding('/old', Foo::clazz));
        $descriptor->load($this->packages->getRootPackage());

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array($descriptor)
            ));

        $this->discoveryManager->expects($this->at(1))
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor, $flags) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ResourceBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame('/new', $descriptor->getBinding()->getQuery());
                PHPUnit_Framework_Assert::assertSame(Foo::clazz, $descriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $descriptor->getBinding()->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('glob', $descriptor->getBinding()->getLanguage());
                PHPUnit_Framework_Assert::assertSame(DiscoveryManager::OVERRIDE, $flags);
            });

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateClassBinding()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('ab12 --class Puli\Cli\Tests\Handler\FindCommandHandlerTest --type my/other --param param2=new2'));
        $binding = new ClassBinding(__CLASS__, Foo::clazz, array(
            'param1' => 'value1',
            'param2' => 'value2',
        ));
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->packages->getRootPackage());
        $uuid = $descriptor->getUuid();

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array($descriptor)
            ));

        $this->discoveryManager->expects($this->at(1))
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor, $flags) use ($uuid) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ClassBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame($uuid, $descriptor->getUuid());
                PHPUnit_Framework_Assert::assertSame('Puli\Cli\Tests\Handler\FindCommandHandlerTest', $descriptor->getBinding()->getClassName());
                PHPUnit_Framework_Assert::assertSame('my/other', $descriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(
                    'param1' => 'value1',
                    'param2' => 'new2',
                ), $descriptor->getBinding()->getParameterValues());
                PHPUnit_Framework_Assert::assertSame(DiscoveryManager::OVERRIDE, $flags);
            });

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateBindingWithUnsetParameter()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('ab12 --unset-param param2'));
        $binding = new ResourceBinding('/path', Foo::clazz, array(
            'param1' => 'value1',
            'param2' => 'value2',
        ), 'glob');
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->packages->getRootPackage());

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array($descriptor)
            ));

        $this->discoveryManager->expects($this->at(1))
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor, $flags) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ResourceBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame('/path', $descriptor->getBinding()->getQuery());
                PHPUnit_Framework_Assert::assertSame(Foo::clazz, $descriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array('param1' => 'value1'), $descriptor->getBinding()->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('glob', $descriptor->getBinding()->getLanguage());
                PHPUnit_Framework_Assert::assertSame(DiscoveryManager::OVERRIDE, $flags);
            });

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateBindingForce()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('ab12 --query /new --force'));
        $descriptor = new BindingDescriptor(new ResourceBinding('/old', Foo::clazz));
        $descriptor->load($this->packages->getRootPackage());

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array($descriptor)
            ));

        $this->discoveryManager->expects($this->at(1))
            ->method('addRootBindingDescriptor')
            ->willReturnCallback(function (BindingDescriptor $descriptor, $flags) {
                PHPUnit_Framework_Assert::assertInstanceOf('Puli\Discovery\Binding\ResourceBinding', $descriptor->getBinding());
                PHPUnit_Framework_Assert::assertSame('/new', $descriptor->getBinding()->getQuery());
                PHPUnit_Framework_Assert::assertSame(Foo::clazz, $descriptor->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $descriptor->getBinding()->getParameterValues());
                PHPUnit_Framework_Assert::assertSame('glob', $descriptor->getBinding()->getLanguage());
                PHPUnit_Framework_Assert::assertSame(DiscoveryManager::OVERRIDE | DiscoveryManager::IGNORE_TYPE_NOT_FOUND | DiscoveryManager::IGNORE_TYPE_NOT_ENABLED, $flags);
            });

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Nothing to update.
     */
    public function testUpdateBindingFailsIfNoUpdateProvided()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('ab12'));
        $descriptor = new BindingDescriptor(new ResourceBinding('/old', Foo::clazz));
        $descriptor->load($this->packages->getRootPackage());

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array($descriptor)
            ));

        $this->discoveryManager->expects($this->never())
            ->method('addRootBindingDescriptor');

        $this->handler->handleUpdate($args);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Can only update bindings in the package "vendor/root".
     */
    public function testUpdateBindingFailsIfNoRootBinding()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('ab12 --query /new'));
        $descriptor = new BindingDescriptor(new ResourceBinding('/old', Foo::clazz));
        $descriptor->load($this->packages->get('vendor/package1'));

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array($descriptor)
            ));

        $this->discoveryManager->expects($this->never())
            ->method('addRootBindingDescriptor');

        $this->handler->handleUpdate($args);
    }

    public function testDeleteBinding()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('ab12'));
        $descriptor = new BindingDescriptor(new ResourceBinding('/path', Foo::clazz));
        $descriptor->load($this->packages->getRootPackage());

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array($descriptor)
            ));

        $this->discoveryManager->expects($this->once())
            ->method('removeRootBindingDescriptor')
            ->with($descriptor->getUuid());

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage More than one binding
     */
    public function testDeleteBindingFailsIfAmbiguous()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('ab12'));

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array(
                    new BindingDescriptor(new ResourceBinding('/path1', Foo::clazz)),
                    new BindingDescriptor(new ResourceBinding('/path2', Foo::clazz)),
                )
            ));

        $this->discoveryManager->expects($this->never())
            ->method('removeRootBindingDescriptor');

        $this->handler->handleDelete($args);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The binding "ab12" does not exist
     */
    public function testDeleteBindingFailsIfNotFound()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('ab12'));

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array()
            ));

        $this->discoveryManager->expects($this->never())
            ->method('removeRootBindingDescriptor');

        $this->handler->handleDelete($args);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Can only delete bindings from the package "vendor/root".
     */
    public function testDeleteBindingFailsIfNoRootBinding()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('ab12'));
        $descriptor = new BindingDescriptor(new ResourceBinding('/path', Foo::clazz));
        $descriptor->load($this->packages->get('vendor/package1'));

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnForExpr(
                $this->uuid('ab12'),
                array($descriptor)
            ));

        $this->discoveryManager->expects($this->never())
            ->method('removeRootBindingDescriptor');

        $this->handler->handleDelete($args);
    }

    public function testEnableBinding()
    {
        $args = self::$enableCommand->parseArgs(new StringArgs('ab12'));
        $descriptor = new BindingDescriptor(new ResourceBinding('/path', Foo::clazz));
        $descriptor->load($this->packages->get('vendor/package1'));

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->with($this->uuid('ab12'))
            ->willReturn(array($descriptor));

        $this->discoveryManager->expects($this->once())
            ->method('enableBindingDescriptor')
            ->with($descriptor->getUuid());

        $this->assertSame(0, $this->handler->handleEnable($args));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The binding "ab12" does not exist
     */
    public function testEnableBindingFailsIfNotFound()
    {
        $args = self::$enableCommand->parseArgs(new StringArgs('ab12'));

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->with($this->uuid('ab12'))
            ->willReturn(array());

        $this->discoveryManager->expects($this->never())
            ->method('enableBindingDescriptor');

        $this->handler->handleEnable($args);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot enable bindings in the package "vendor/root".
     */
    public function testEnableBindingFailsIfRoot()
    {
        $args = self::$enableCommand->parseArgs(new StringArgs('ab12'));
        $descriptor = new BindingDescriptor(new ResourceBinding('/path', Foo::clazz));
        $descriptor->load($this->packages->getRootPackage());

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->with($this->uuid('ab12'))
            ->willReturn(array($descriptor));

        $this->discoveryManager->expects($this->never())
            ->method('enableBindingDescriptor');

        $this->handler->handleEnable($args);
    }

    public function testDisableBinding()
    {
        $args = self::$disableCommand->parseArgs(new StringArgs('ab12'));
        $descriptor = new BindingDescriptor(new ResourceBinding('/path', Foo::clazz));
        $descriptor->load($this->packages->get('vendor/package1'));

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->with($this->uuid('ab12'))
            ->willReturn(array($descriptor));

        $this->discoveryManager->expects($this->once())
            ->method('disableBindingDescriptor')
            ->with($descriptor->getUuid());

        $this->assertSame(0, $this->handler->handleDisable($args));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The binding "ab12" does not exist
     */
    public function testDisableBindingsFailsIfNotFound()
    {
        $args = self::$disableCommand->parseArgs(new StringArgs('ab12'));

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->with($this->uuid('ab12'))
            ->willReturn(array());

        $this->discoveryManager->expects($this->never())
            ->method('disableBindingDescriptor');

        $this->handler->handleDisable($args);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot disable bindings in the package "vendor/root".
     */
    public function testDisableBindingFailsIfRoot()
    {
        $args = self::$disableCommand->parseArgs(new StringArgs('ab12'));
        $descriptor = new BindingDescriptor(new ResourceBinding('/path', Foo::clazz));
        $descriptor->load($this->packages->getRootPackage());

        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->with($this->uuid('ab12'))
            ->willReturn(array($descriptor));

        $this->discoveryManager->expects($this->never())
            ->method('disableBindingDescriptor');

        $this->handler->handleDisable($args);
    }

    private function initDefaultBindings()
    {
        $this->discoveryManager->expects($this->any())
            ->method('findBindingDescriptors')
            ->willReturnCallback($this->returnFromMap(array(
                array($this->packageAndState('vendor/root', BindingState::ENABLED), array(
                    new BindingDescriptor(new ResourceBinding('/root/enabled', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1))),
                    new BindingDescriptor(new ResourceBinding('/overridden', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID2))),
                    new BindingDescriptor(new ClassBinding(__CLASS__, Bar::clazz, array(), Uuid::fromString(self::BINDING_UUID17))),
                )),
                array($this->packageAndState('vendor/root', BindingState::DISABLED), array(
                    new BindingDescriptor(new ResourceBinding('/root/disabled', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID3))),
                )),
                array($this->packageAndState('vendor/root', BindingState::TYPE_NOT_FOUND), array(
                    new BindingDescriptor(new ResourceBinding('/root/type-not-found', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID5))),
                )),
                array($this->packageAndState('vendor/root', BindingState::TYPE_NOT_ENABLED), array(
                    new BindingDescriptor(new ResourceBinding('/root/type-not-enabled', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID17))),
                )),
                array($this->packageAndState('vendor/root', BindingState::INVALID), array(
                    new BindingDescriptor(new ResourceBinding('/root/invalid', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID6))),
                )),
                array($this->packageAndState('vendor/package1', BindingState::ENABLED), array(
                    new BindingDescriptor(new ResourceBinding('/package1/enabled', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID7))),
                )),
                array($this->packageAndState('vendor/package1', BindingState::DISABLED), array(
                    new BindingDescriptor(new ResourceBinding('/package1/disabled', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID8))),
                )),
                array($this->packageAndState('vendor/package1', BindingState::TYPE_NOT_FOUND), array(
                    new BindingDescriptor(new ResourceBinding('/package1/type-not-found', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID10))),
                )),
                array($this->packageAndState('vendor/package1', BindingState::TYPE_NOT_ENABLED), array(
                    new BindingDescriptor(new ResourceBinding('/package1/type-not-enable', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID18))),
                )),
                array($this->packageAndState('vendor/package1', BindingState::INVALID), array(
                    new BindingDescriptor(new ResourceBinding('/package1/invalid', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID11))),
                )),
                array($this->packageAndState('vendor/package2', BindingState::ENABLED), array(
                    new BindingDescriptor(new ResourceBinding('/package2/enabled', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID12))),
                )),
                array($this->packageAndState('vendor/package2', BindingState::DISABLED), array(
                    new BindingDescriptor(new ResourceBinding('/package2/disabled', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID13))),
                )),
                array($this->packageAndState('vendor/package2', BindingState::TYPE_NOT_FOUND), array(
                    new BindingDescriptor(new ResourceBinding('/package2/type-not-found', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID15))),
                )),
                array($this->packageAndState('vendor/package2', BindingState::TYPE_NOT_ENABLED), array(
                    new BindingDescriptor(new ResourceBinding('/package2/type-not-enabled', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID19))),
                )),
                array($this->packageAndState('vendor/package2', BindingState::INVALID), array(
                    new BindingDescriptor(new ResourceBinding('/package2/invalid', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID16))),
                )),
            )));
    }

    private function packageAndState($packageName, $state)
    {
        return Expr::method('getContainingPackage', Expr::method('getName', Expr::same($packageName)))
            ->andMethod('getState', Expr::same($state));
    }

    private function uuid($uuid)
    {
        return Expr::method('getUuid', Expr::startsWith($uuid));
    }

    private function returnForExpr(Expression $expr, $result)
    {
        // This method is needed since PHPUnit's ->with() method does not
        // internally clone the passed argument. Since we call the same method
        // findBindings() twice with the *same* object, but modify the state of
        // that object in between, PHPUnit fails since the state of the object
        // *after* the test does not match the first assertion anymore
        return function (Expression $actualExpr) use ($expr, $result) {
            PHPUnit_Framework_Assert::assertTrue($actualExpr->equivalentTo($expr));

            return $result;
        };
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
