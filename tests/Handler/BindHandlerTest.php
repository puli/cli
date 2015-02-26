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
use PHPUnit_Framework_TestCase;
use Puli\Cli\Handler\BindHandler;
use Puli\Cli\PuliApplicationConfig;
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
use Webmozart\Console\ConsoleApplication;
use Webmozart\Console\IO\BufferedIO;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindHandlerTest extends PHPUnit_Framework_TestCase
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
     * @var PHPUnit_Framework_MockObject_MockObject|DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var BufferedIO
     */
    private $io;

    /**
     * @var BindHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        $application = new ConsoleApplication(new PuliApplicationConfig());

        self::$listCommand = $application->getCommand('bind')->getSubCommand('list');
        self::$saveCommand = $application->getCommand('bind')->getSubCommand('save');
    }

    protected function setUp()
    {
        $this->discoveryManager = $this->getMock('Puli\RepositoryManager\Api\Discovery\DiscoveryManager');
        $this->packages = new PackageCollection(array(
            new RootPackage(new RootPackageFile('vendor/root'), '/root'),
            new Package(new PackageFile('vendor/package1'), '/package1'),
            new Package(new PackageFile('vendor/package2'), '/package2'),
        ));
        $this->discoveryManager->expects($this->any())
            ->method('getBindings')
            ->willReturnMap(array(
                array('vendor/root', BindingState::ENABLED, array(
                    new BindingDescriptor('/root/enabled', 'my/type'),
                    new BindingDescriptor('/duplicate', 'my/type'),
                )),
                array('vendor/root', BindingState::DISABLED, array(
                    new BindingDescriptor('/root/disabled', 'my/type'),
                )),
                array('vendor/root', BindingState::UNDECIDED, array(
                    new BindingDescriptor('/root/undecided', 'my/type'),
                )),
                array('vendor/root', BindingState::DUPLICATE, array()),
                array('vendor/root', BindingState::HELD_BACK, array(
                    new BindingDescriptor('/root/held-back', 'my/type'),
                )),
                array('vendor/root', BindingState::INVALID, array(
                    new BindingDescriptor('/root/invalid', 'my/type'),
                )),
                array('vendor/package1', BindingState::ENABLED, array(
                    new BindingDescriptor('/package1/enabled', 'my/type'),
                )),
                array('vendor/package1', BindingState::DISABLED, array(
                    new BindingDescriptor('/package1/disabled', 'my/type'),
                )),
                array('vendor/package1', BindingState::UNDECIDED, array(
                    new BindingDescriptor('/package1/undecided', 'my/type'),
                )),
                array('vendor/package1', BindingState::DUPLICATE, array(
                    new BindingDescriptor('/duplicate', 'my/type'),
                )),
                array('vendor/package1', BindingState::HELD_BACK, array(
                    new BindingDescriptor('/package1/held-back', 'my/type'),
                )),
                array('vendor/package1', BindingState::INVALID, array(
                    new BindingDescriptor('/package1/invalid', 'my/type'),
                )),
                array('vendor/package2', BindingState::ENABLED, array(
                    new BindingDescriptor('/package2/enabled', 'my/type'),
                )),
                array('vendor/package2', BindingState::DISABLED, array(
                    new BindingDescriptor('/package2/disabled', 'my/type'),
                )),
                array('vendor/package2', BindingState::UNDECIDED, array(
                    new BindingDescriptor('/package2/undecided', 'my/type'),
                )),
                array('vendor/package2', BindingState::DUPLICATE, array(
                    new BindingDescriptor('/duplicate', 'my/type'),
                )),
                array('vendor/package2', BindingState::HELD_BACK, array(
                    new BindingDescriptor('/package2/held-back', 'my/type'),
                )),
                array('vendor/package2', BindingState::INVALID, array(
                    new BindingDescriptor('/package2/invalid', 'my/type'),
                )),
            ));
        $this->io = new BufferedIO();
        $this->handler = new BindHandler($this->discoveryManager, $this->packages);
    }

    public function testListAllBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    vendor/root
    0f1933 /root/enabled my/type
    288290 /duplicate    my/type

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

The following bindings are duplicates and ignored:

    vendor/package1
    288290 /duplicate my/type

    vendor/package2
    288290 /duplicate my/type

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

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListRootBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--root'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    0f1933 /root/enabled my/type
    288290 /duplicate    my/type

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

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListPackageBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--package=vendor/package1'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    7d5312 /package1/enabled my/type

Disabled bindings:
 (use "puli bind --enable <uuid>" to enable)

    8eb772 /package1/disabled my/type

Bindings that are neither enabled nor disabled:
 (use "puli bind --enable <uuid>" to enable)

    2611ca /package1/undecided my/type

The following bindings are duplicates and ignored:

    288290 /duplicate my/type

The following bindings are held back:
 (install or fix their type definitions to enable)

    bdb328 /package1/held-back my/type

The following bindings have invalid parameters:
 (remove the binding and add again with correct parameters)

    9d2297 /package1/invalid my/type


EOF;

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListRootAndPackageBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--root --package=vendor/package1'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    vendor/root
    0f1933 /root/enabled my/type
    288290 /duplicate    my/type

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

The following bindings are duplicates and ignored:

    vendor/package1
    288290 /duplicate my/type

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

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListMultiplePackageBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--package=vendor/package1 --package=vendor/package2'));

        $this->handler->handleList($args, $this->io);

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

The following bindings are duplicates and ignored:

    vendor/package1
    288290 /duplicate my/type

    vendor/package2
    288290 /duplicate my/type

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

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root
0f1933 /root/enabled my/type
288290 /duplicate    my/type

vendor/package1
7d5312 /package1/enabled my/type

vendor/package2
1db044 /package2/enabled my/type


EOF;

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListDisabledBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--disabled'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root
4e1bf9 /root/disabled my/type

vendor/package1
8eb772 /package1/disabled my/type

vendor/package2
cbc774 /package2/disabled my/type


EOF;

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListUndecidedBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--undecided'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root
414f83 /root/undecided my/type

vendor/package1
2611ca /package1/undecided my/type

vendor/package2
446842 /package2/undecided my/type


EOF;

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListDuplicateBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--duplicate'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/package1
288290 /duplicate my/type

vendor/package2
288290 /duplicate my/type


EOF;

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListHeldBackBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--held-back'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root
853a98 /root/held-back my/type

vendor/package1
bdb328 /package1/held-back my/type

vendor/package2
5aa563 /package2/held-back my/type


EOF;

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListInvalidBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--invalid'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
vendor/root
23fac6 /root/invalid my/type

vendor/package1
9d2297 /package1/invalid my/type

vendor/package2
c19a35 /package2/invalid my/type


EOF;

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }


    public function testListEnabledAndDisabledBindings()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --disabled'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
Enabled bindings:

    vendor/root
    0f1933 /root/enabled my/type
    288290 /duplicate    my/type

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

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledBindingsFromRoot()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --root'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
0f1933 /root/enabled my/type
288290 /duplicate    my/type

EOF;

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledBindingsFromPackage()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --package=vendor/package2'));

        $this->handler->handleList($args, $this->io);

        $expected = <<<EOF
1db044 /package2/enabled my/type

EOF;

        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }
}
