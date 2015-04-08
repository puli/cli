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
use Puli\Cli\Handler\TypeCommandHandler;
use Puli\Manager\Api\Discovery\BindingParameterDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeState;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TypeCommandHandlerTest extends AbstractCommandHandlerTest
{
    /**
     * @var Command
     */
    private static $listCommand;

    /**
     * @var Command
     */
    private static $defineCommand;

    /**
     * @var Command
     */
    private static $removeCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var TypeCommandHandler
     */
    private $handler;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$listCommand = self::$application->getCommand('type')->getSubCommand('list');
        self::$defineCommand = self::$application->getCommand('type')->getSubCommand('define');
        self::$removeCommand = self::$application->getCommand('type')->getSubCommand('remove');
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
        $this->handler = new TypeCommandHandler($this->discoveryManager, $this->packages);

        $this->discoveryManager->expects($this->any())
            ->method('findBindingTypes')
            ->willReturnCallback($this->returnFromMap(array(
                array($this->packageAndState('vendor/root', BindingTypeState::ENABLED), array(
                    new BindingTypeDescriptor('root/enabled1', 'Description of root/enabled1', array(
                        new BindingParameterDescriptor('req-param', true, null, 'Description of req-param'),
                        new BindingParameterDescriptor('opt-param', false, 'default', 'Description of opt-param'),
                    )),
                    new BindingTypeDescriptor('root/enabled2', 'Description of root/enabled2'),
                )),
                array($this->packageAndState('vendor/root', BindingTypeState::DUPLICATE), array(
                    new BindingTypeDescriptor('root/duplicate'),
                )),
                array($this->packageAndState('vendor/package1', BindingTypeState::ENABLED), array(
                    new BindingTypeDescriptor('package1/enabled'),
                )),
                array($this->packageAndState('vendor/package1', BindingTypeState::DUPLICATE), array(
                    new BindingTypeDescriptor('package1/duplicate'),
                )),
                array($this->packageAndState('vendor/package2', BindingTypeState::ENABLED), array(
                    new BindingTypeDescriptor('package2/enabled'),
                )),
                array($this->packageAndState('vendor/package2', BindingTypeState::DUPLICATE), array(
                    new BindingTypeDescriptor('package2/duplicate'),
                )),
            )));
    }

    public function testListAllTypes()
    {
        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $expected = <<<EOF
Enabled binding types:

    vendor/root
    root/enabled1 Description of root/enabled1 (req-param, opt-param="default")
    root/enabled2 Description of root/enabled2

    vendor/package1
    package1/enabled

    vendor/package2
    package2/enabled

The following types have duplicate definitions and are disabled:

    vendor/root
    root/duplicate

    vendor/package1
    package1/duplicate

    vendor/package2
    package2/duplicate

Use "puli bind <resource> <type>" to bind a resource to a type.


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListRootPackageTypes()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--root'));

        $expected = <<<EOF
Enabled binding types:

    root/enabled1 Description of root/enabled1 (req-param, opt-param="default")
    root/enabled2 Description of root/enabled2

The following types have duplicate definitions and are disabled:

    root/duplicate

Use "puli bind <resource> <type>" to bind a resource to a type.


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListPackageTypes()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--package vendor/package1'));

        $expected = <<<EOF
Enabled binding types:

    package1/enabled

The following types have duplicate definitions and are disabled:

    package1/duplicate

Use "puli bind <resource> <type>" to bind a resource to a type.


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListRootAndPackageTypes()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--root --package vendor/package1'));

        $expected = <<<EOF
Enabled binding types:

    vendor/root
    root/enabled1 Description of root/enabled1 (req-param, opt-param="default")
    root/enabled2 Description of root/enabled2

    vendor/package1
    package1/enabled

The following types have duplicate definitions and are disabled:

    vendor/root
    root/duplicate

    vendor/package1
    package1/duplicate

Use "puli bind <resource> <type>" to bind a resource to a type.


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListMultiplePackageTypes()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--package vendor/package1 --package vendor/package2'));

        $expected = <<<EOF
Enabled binding types:

    vendor/package1
    package1/enabled

    vendor/package2
    package2/enabled

The following types have duplicate definitions and are disabled:

    vendor/package1
    package1/duplicate

    vendor/package2
    package2/duplicate

Use "puli bind <resource> <type>" to bind a resource to a type.


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledTypes()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled'));

        $expected = <<<EOF
vendor/root
root/enabled1 Description of root/enabled1 (req-param, opt-param="default")
root/enabled2 Description of root/enabled2

vendor/package1
package1/enabled

vendor/package2
package2/enabled


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListDuplicateTypes()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--duplicate'));

        $expected = <<<EOF
vendor/root
root/duplicate

vendor/package1
package1/duplicate

vendor/package2
package2/duplicate


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledAndDuplicateTypes()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --duplicate'));

        $expected = <<<EOF
Enabled binding types:

    vendor/root
    root/enabled1 Description of root/enabled1 (req-param, opt-param="default")
    root/enabled2 Description of root/enabled2

    vendor/package1
    package1/enabled

    vendor/package2
    package2/enabled

The following types have duplicate definitions and are disabled:

    vendor/root
    root/duplicate

    vendor/package1
    package1/duplicate

    vendor/package2
    package2/duplicate

Use "puli bind <resource> <type>" to bind a resource to a type.


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledTypesInRoot()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --root'));

        $expected = <<<EOF
root/enabled1 Description of root/enabled1 (req-param, opt-param="default")
root/enabled2 Description of root/enabled2

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledTypesInPackage()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --package vendor/package1'));

        $expected = <<<EOF
package1/enabled

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testDefineType()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('my/type'));

        $this->discoveryManager->expects($this->once())
            ->method('addBindingType')
            ->with(new BindingTypeDescriptor('my/type'));

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testDefineTypeWithDescription()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('my/type --description "The description"'));

        $this->discoveryManager->expects($this->once())
            ->method('addBindingType')
            ->with(new BindingTypeDescriptor('my/type', 'The description'));

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testDefineTypeWithRequiredParameter()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('my/type --param required'));

        $this->discoveryManager->expects($this->once())
            ->method('addBindingType')
            ->with(new BindingTypeDescriptor('my/type', null, array(
                new BindingParameterDescriptor('required', true),
            )));

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testDefineTypeWithOptionalParameter()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('my/type --param optional=true'));

        $this->discoveryManager->expects($this->once())
            ->method('addBindingType')
            ->with(new BindingTypeDescriptor('my/type', null, array(
                new BindingParameterDescriptor('optional', false, true),
            )));

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testDefineTypeWithParameterDescription()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('my/type --description "The description" --param param --description "The parameter description"'));

        $this->discoveryManager->expects($this->once())
            ->method('addBindingType')
            ->with(new BindingTypeDescriptor('my/type', 'The description', array(
                new BindingParameterDescriptor('param', true, null, 'The parameter description')
            )));

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testDefineTypeForce()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('--force my/type'));

        $this->discoveryManager->expects($this->once())
            ->method('addBindingType')
            ->with(new BindingTypeDescriptor('my/type'), DiscoveryManager::NO_DUPLICATE_CHECK);

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testRemoveType()
    {
        $args = self::$removeCommand->parseArgs(new StringArgs('my/type'));

        $this->discoveryManager->expects($this->once())
            ->method('removeBindingType')
            ->with('my/type');

        $this->assertSame(0, $this->handler->handleRemove($args));
    }


    private function packageAndState($packageName, $state)
    {
        return Expr::same(BindingTypeDescriptor::CONTAINING_PACKAGE, $packageName)
            ->andSame(BindingTypeDescriptor::STATE, $state);
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
