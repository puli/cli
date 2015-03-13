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
use Puli\Cli\Handler\BindHandler;
use Puli\RepositoryManager\Api\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingState;
use Puli\RepositoryManager\Api\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Package\PackageFile;
use Puli\RepositoryManager\Api\Package\RootPackage;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindHandlerTest extends AbstractHandlerTest
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
     * @var BindHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$listCommand = self::$application->getCommand('bind')->getSubCommand('list');
        self::$saveCommand = self::$application->getCommand('bind')->getSubCommand('save');
        self::$deleteCommand = self::$application->getCommand('bind')->getSubCommand('delete');
        self::$enableCommand = self::$application->getCommand('bind')->getSubCommand('enable');
        self::$disableCommand = self::$application->getCommand('bind')->getSubCommand('disable');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->discoveryManager = $this->getMock('Puli\RepositoryManager\Api\Discovery\DiscoveryManager');
        $this->packages = new PackageCollection(array(
            new RootPackage(new RootPackageFile('vendor/root'), '/root'),
            new Package(new PackageFile('vendor/package1'), '/package1'),
            new Package(new PackageFile('vendor/package2'), '/package2'),
        ));
        $this->handler = new BindHandler($this->discoveryManager, $this->packages);
    }

    public function testListAllBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    vendor/root
    0f1933 /root/enabled my/type
    71dfa8 /overridden   my/type

    vendor/package1
    7d5312 /package1/enabled my/type

    vendor/package2
    1db044 /package2/enabled my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    vendor/root
    4e1bf9 /root/disabled my/type

    vendor/package1
    8eb772 /package1/disabled my/type

    vendor/package2
    cbc774 /package2/disabled my/type

Bindings that are neither enabled nor disabled:
 (use "puli bind --enable <uuid>" to enable)

    vendor/root
    414f83 /root/undecided my/type

    vendor/package1
    2611ca /package1/undecided my/type

    vendor/package2
    446842 /package2/undecided my/type

The following bindings are overridden:

    vendor/package1
    71dfa8 /overridden my/type

    vendor/package2
    71dfa8 /overridden my/type

The following bindings are held back:
 (install or fix their type definitions to enable)

    vendor/root
    853a98 /root/held-back my/type

    vendor/package1
    bdb328 /package1/held-back my/type

    vendor/package2
    5aa563 /package2/held-back my/type

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    vendor/root
    23fac6 /root/invalid my/type

    vendor/package1
    9d2297 /package1/invalid my/type

    vendor/package2
    c19a35 /package2/invalid my/type


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

    0f1933 /root/enabled my/type
    71dfa8 /overridden   my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    4e1bf9 /root/disabled my/type

Bindings that are neither enabled nor disabled:
 (use "puli bind --enable <uuid>" to enable)

    414f83 /root/undecided my/type

The following bindings are held back:
 (install or fix their type definitions to enable)

    853a98 /root/held-back my/type

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    23fac6 /root/invalid my/type


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

    7d5312 /package1/enabled my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    8eb772 /package1/disabled my/type

Bindings that are neither enabled nor disabled:
 (use "puli bind --enable <uuid>" to enable)

    2611ca /package1/undecided my/type

The following bindings are overridden:

    71dfa8 /overridden my/type

The following bindings are held back:
 (install or fix their type definitions to enable)

    bdb328 /package1/held-back my/type

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    9d2297 /package1/invalid my/type


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
    0f1933 /root/enabled my/type
    71dfa8 /overridden   my/type

    vendor/package1
    7d5312 /package1/enabled my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    vendor/root
    4e1bf9 /root/disabled my/type

    vendor/package1
    8eb772 /package1/disabled my/type

Bindings that are neither enabled nor disabled:
 (use "puli bind --enable <uuid>" to enable)

    vendor/root
    414f83 /root/undecided my/type

    vendor/package1
    2611ca /package1/undecided my/type

The following bindings are overridden:

    vendor/package1
    71dfa8 /overridden my/type

The following bindings are held back:
 (install or fix their type definitions to enable)

    vendor/root
    853a98 /root/held-back my/type

    vendor/package1
    bdb328 /package1/held-back my/type

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    vendor/root
    23fac6 /root/invalid my/type

    vendor/package1
    9d2297 /package1/invalid my/type


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
    7d5312 /package1/enabled my/type

    vendor/package2
    1db044 /package2/enabled my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    vendor/package1
    8eb772 /package1/disabled my/type

    vendor/package2
    cbc774 /package2/disabled my/type

Bindings that are neither enabled nor disabled:
 (use "puli bind --enable <uuid>" to enable)

    vendor/package1
    2611ca /package1/undecided my/type

    vendor/package2
    446842 /package2/undecided my/type

The following bindings are overridden:

    vendor/package1
    71dfa8 /overridden my/type

    vendor/package2
    71dfa8 /overridden my/type

The following bindings are held back:
 (install or fix their type definitions to enable)

    vendor/package1
    bdb328 /package1/held-back my/type

    vendor/package2
    5aa563 /package2/held-back my/type

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    vendor/package1
    9d2297 /package1/invalid my/type

    vendor/package2
    c19a35 /package2/invalid my/type


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
0f1933 /root/enabled my/type
71dfa8 /overridden   my/type

vendor/package1
7d5312 /package1/enabled my/type

vendor/package2
1db044 /package2/enabled my/type


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
4e1bf9 /root/disabled my/type

vendor/package1
8eb772 /package1/disabled my/type

vendor/package2
cbc774 /package2/disabled my/type


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
414f83 /root/undecided my/type

vendor/package1
2611ca /package1/undecided my/type

vendor/package2
446842 /package2/undecided my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListOverriddenBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--overridden'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/package1
71dfa8 /overridden my/type

vendor/package2
71dfa8 /overridden my/type


EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListHeldBackBindings()
    {
        $this->initDefaultBindings();

        $args = self::$listCommand->parseArgs(new StringArgs('--held-back'));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root
853a98 /root/held-back my/type

vendor/package1
bdb328 /package1/held-back my/type

vendor/package2
5aa563 /package2/held-back my/type


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
23fac6 /root/invalid my/type

vendor/package1
9d2297 /package1/invalid my/type

vendor/package2
c19a35 /package2/invalid my/type


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
    0f1933 /root/enabled my/type
    71dfa8 /overridden   my/type

    vendor/package1
    7d5312 /package1/enabled my/type

    vendor/package2
    1db044 /package2/enabled my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    vendor/root
    4e1bf9 /root/disabled my/type

    vendor/package1
    8eb772 /package1/disabled my/type

    vendor/package2
    cbc774 /package2/disabled my/type


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
0f1933 /root/enabled my/type
71dfa8 /overridden   my/type

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
1db044 /package2/enabled my/type

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
            ->with(new BindingDescriptor('/path', 'my/type', array(), 'glob'));

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
            ->with(new BindingDescriptor('/path', 'my/type', array(), 'glob'));

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
            ->with(new BindingDescriptor('/path', 'my/type', array(), 'lang'));

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
            ->with(new BindingDescriptor('/path', 'my/type', array(
                'key1' => 'value',
                'key2' => true,
            ), 'glob'));

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

    public function testRemoveBinding()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('ab12'));
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

        $statusCode = $this->handler->handleDelete($args, $this->io);

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
        $args = self::$deleteCommand->parseArgs(new StringArgs('ab12'));

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

        $this->handler->handleDelete($args, $this->io);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The binding "ab12" does not exist
     */
    public function testRemoveBindingFailsIfNotFound()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('ab12'));

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

        $this->handler->handleDelete($args, $this->io);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Can only delete bindings from the root package
     */
    public function testRemoveBindingFailsIfNoRootBinding()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('ab12'));

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

        $this->handler->handleDelete($args, $this->io);
    }

    public function testEnableBindings()
    {
        $args = self::$enableCommand->parseArgs(new StringArgs('ab12'));
        $descriptor1 = new BindingDescriptor('/path', 'my/type', array(), 'glob');
        $descriptor2 = new BindingDescriptor('/path', 'my/type', array(), 'glob');

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindings')
            ->with($this->packagesAndUuid(array('vendor/package1', 'vendor/package2'), 'ab12'))
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

    public function testEnableBindingsInSpecificPackage()
    {
        $args = self::$enableCommand->parseArgs(new StringArgs('ab12 --package vendor/package2'));
        $descriptor1 = new BindingDescriptor('/path', 'my/type', array(), 'glob');
        $descriptor2 = new BindingDescriptor('/path', 'my/type', array(), 'glob');

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindings')
            ->with($this->packageAndUuid('vendor/package2', 'ab12'))
            ->willReturn(array($descriptor1, $descriptor2));

        $this->discoveryManager->expects($this->at(1))
            ->method('enableBinding')
            ->with($descriptor1->getUuid(), array('vendor/package2'));

        $this->discoveryManager->expects($this->at(2))
            ->method('enableBinding')
            ->with($descriptor2->getUuid(), array('vendor/package2'));

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
            ->with($this->packagesAndUuid(array('vendor/package1', 'vendor/package2'), 'ab12'))
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
            ->with($this->packagesAndUuid(array('vendor/package1', 'vendor/package2'), 'ab12'))
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

    public function testDisableBindingsInSpecificPackage()
    {
        $args = self::$disableCommand->parseArgs(new StringArgs('ab12 --package vendor/package2'));
        $descriptor1 = new BindingDescriptor('/path', 'my/type', array(), 'glob');
        $descriptor2 = new BindingDescriptor('/path', 'my/type', array(), 'glob');

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindings')
            ->with($this->packageAndUuid('vendor/package2', 'ab12'))
            ->willReturn(array($descriptor1, $descriptor2));

        $this->discoveryManager->expects($this->at(1))
            ->method('disableBinding')
            ->with($descriptor1->getUuid(), array('vendor/package2'));

        $this->discoveryManager->expects($this->at(2))
            ->method('disableBinding')
            ->with($descriptor2->getUuid(), array('vendor/package2'));

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
            ->with($this->packagesAndUuid(array('vendor/package1', 'vendor/package2'), 'ab12'))
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
                    new BindingDescriptor('/root/enabled', 'my/type'),
                    new BindingDescriptor('/overridden', 'my/type'),
                )),
                array($this->packageAndState('vendor/root', BindingState::DISABLED), array(
                    new BindingDescriptor('/root/disabled', 'my/type'),
                )),
                array($this->packageAndState('vendor/root', BindingState::UNDECIDED), array(
                    new BindingDescriptor('/root/undecided', 'my/type'),
                )),
                array($this->packageAndState('vendor/root', BindingState::OVERRIDDEN), array()),
                array($this->packageAndState('vendor/root', BindingState::HELD_BACK), array(
                    new BindingDescriptor('/root/held-back', 'my/type'),
                )),
                array($this->packageAndState('vendor/root', BindingState::INVALID), array(
                    new BindingDescriptor('/root/invalid', 'my/type'),
                )),
                array($this->packageAndState('vendor/package1', BindingState::ENABLED), array(
                    new BindingDescriptor('/package1/enabled', 'my/type'),
                )),
                array($this->packageAndState('vendor/package1', BindingState::DISABLED), array(
                    new BindingDescriptor('/package1/disabled', 'my/type'),
                )),
                array($this->packageAndState('vendor/package1', BindingState::UNDECIDED), array(
                    new BindingDescriptor('/package1/undecided', 'my/type'),
                )),
                array($this->packageAndState('vendor/package1', BindingState::OVERRIDDEN), array(
                    new BindingDescriptor('/overridden', 'my/type'),
                )),
                array($this->packageAndState('vendor/package1', BindingState::HELD_BACK), array(
                    new BindingDescriptor('/package1/held-back', 'my/type'),
                )),
                array($this->packageAndState('vendor/package1', BindingState::INVALID), array(
                    new BindingDescriptor('/package1/invalid', 'my/type'),
                )),
                array($this->packageAndState('vendor/package2', BindingState::ENABLED), array(
                    new BindingDescriptor('/package2/enabled', 'my/type'),
                )),
                array($this->packageAndState('vendor/package2', BindingState::DISABLED), array(
                    new BindingDescriptor('/package2/disabled', 'my/type'),
                )),
                array($this->packageAndState('vendor/package2', BindingState::UNDECIDED), array(
                    new BindingDescriptor('/package2/undecided', 'my/type'),
                )),
                array($this->packageAndState('vendor/package2', BindingState::OVERRIDDEN), array(
                    new BindingDescriptor('/overridden', 'my/type'),
                )),
                array($this->packageAndState('vendor/package2', BindingState::HELD_BACK), array(
                    new BindingDescriptor('/package2/held-back', 'my/type'),
                )),
                array($this->packageAndState('vendor/package2', BindingState::INVALID), array(
                    new BindingDescriptor('/package2/invalid', 'my/type'),
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

    private function packagesAndUuid(array $packageNames, $uuid)
    {
        return Expr::oneOf(BindingDescriptor::CONTAINING_PACKAGE, $packageNames)
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
