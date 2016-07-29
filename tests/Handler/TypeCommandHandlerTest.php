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
use Puli\Discovery\Api\Type\BindingParameter;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeState;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use Webmozart\Console\Api\Command\Command;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * @since  1.0
 *
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
    private static $updateCommand;

    /**
     * @var Command
     */
    private static $deleteCommand;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var ModuleList
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
        self::$updateCommand = self::$application->getCommand('type')->getSubCommand('update');
        self::$deleteCommand = self::$application->getCommand('type')->getSubCommand('delete');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->discoveryManager = $this->getMock('Puli\Manager\Api\Discovery\DiscoveryManager');
        $this->packages = new ModuleList(array(
            new RootModule(new RootModuleFile('vendor/root'), '/root'),
            new Module(new ModuleFile('vendor/package1'), '/package1'),
            new Module(new ModuleFile('vendor/package2'), '/package2'),
        ));
        $this->handler = new TypeCommandHandler($this->discoveryManager, $this->packages);

        $this->discoveryManager->expects($this->any())
            ->method('findTypeDescriptors')
            ->willReturnCallback($this->returnFromMap(array(
                array($this->packageAndState('vendor/root', BindingTypeState::ENABLED), array(
                    new BindingTypeDescriptor(
                        new BindingType('root/enabled1', array(
                            new BindingParameter('req-param', BindingParameter::REQUIRED),
                            new BindingParameter('opt-param', BindingParameter::OPTIONAL, 'default'),
                        )),
                        'Description of root/enabled1',
                        array(
                            'req-param' => 'Description of req-param',
                            'opt-param' => 'Description of opt-param',
                        )
                    ),
                    new BindingTypeDescriptor(new BindingType('root/enabled2'), 'Description of root/enabled2'),
                )),
                array($this->packageAndState('vendor/root', BindingTypeState::DUPLICATE), array(
                    new BindingTypeDescriptor(new BindingType('root/duplicate')),
                )),
                array($this->packageAndState('vendor/package1', BindingTypeState::ENABLED), array(
                    new BindingTypeDescriptor(new BindingType('package1/enabled')),
                )),
                array($this->packageAndState('vendor/package1', BindingTypeState::DUPLICATE), array(
                    new BindingTypeDescriptor(new BindingType('package1/duplicate')),
                )),
                array($this->packageAndState('vendor/package2', BindingTypeState::ENABLED), array(
                    new BindingTypeDescriptor(new BindingType('package2/enabled')),
                )),
                array($this->packageAndState('vendor/package2', BindingTypeState::DUPLICATE), array(
                    new BindingTypeDescriptor(new BindingType('package2/duplicate')),
                )),
            )));
    }

    public function testListAllTypes()
    {
        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $expected = <<<'EOF'
The following binding types are currently enabled:

    Package: vendor/root

        Type           Description     Parameters
        root/enabled1  Description of  opt-param="default"
                       root/enabled1   req-param
        root/enabled2  Description of
                       root/enabled2

    Package: vendor/package1

        Type              Description  Parameters
        package1/enabled

    Package: vendor/package2

        Type              Description  Parameters
        package2/enabled

The following types have duplicate definitions and are disabled:

    Package: vendor/root

        Type            Description  Parameters
        root/duplicate

    Package: vendor/package1

        Type                Description  Parameters
        package1/duplicate

    Package: vendor/package2

        Type                Description  Parameters
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

        $expected = <<<'EOF'
The following binding types are currently enabled:

    Type           Description                   Parameters
    root/enabled1  Description of root/enabled1  opt-param="default"
                                                 req-param
    root/enabled2  Description of root/enabled2

The following types have duplicate definitions and are disabled:

    Type            Description  Parameters
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

        $expected = <<<'EOF'
The following binding types are currently enabled:

    Type              Description  Parameters
    package1/enabled

The following types have duplicate definitions and are disabled:

    Type                Description  Parameters
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

        $expected = <<<'EOF'
The following binding types are currently enabled:

    Package: vendor/root

        Type           Description     Parameters
        root/enabled1  Description of  opt-param="default"
                       root/enabled1   req-param
        root/enabled2  Description of
                       root/enabled2

    Package: vendor/package1

        Type              Description  Parameters
        package1/enabled

The following types have duplicate definitions and are disabled:

    Package: vendor/root

        Type            Description  Parameters
        root/duplicate

    Package: vendor/package1

        Type                Description  Parameters
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

        $expected = <<<'EOF'
The following binding types are currently enabled:

    Package: vendor/package1

        Type              Description  Parameters
        package1/enabled

    Package: vendor/package2

        Type              Description  Parameters
        package2/enabled

The following types have duplicate definitions and are disabled:

    Package: vendor/package1

        Type                Description  Parameters
        package1/duplicate

    Package: vendor/package2

        Type                Description  Parameters
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

        $expected = <<<'EOF'
Package: vendor/root

    Type           Description                   Parameters
    root/enabled1  Description of root/enabled1  opt-param="default"
                                                 req-param
    root/enabled2  Description of root/enabled2

Package: vendor/package1

    Type              Description  Parameters
    package1/enabled

Package: vendor/package2

    Type              Description  Parameters
    package2/enabled


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListDuplicateTypes()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--duplicate'));

        $expected = <<<'EOF'
Package: vendor/root

    Type            Description  Parameters
    root/duplicate

Package: vendor/package1

    Type                Description  Parameters
    package1/duplicate

Package: vendor/package2

    Type                Description  Parameters
    package2/duplicate


EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledAndDuplicateTypes()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --duplicate'));

        $expected = <<<'EOF'
The following binding types are currently enabled:

    Package: vendor/root

        Type           Description     Parameters
        root/enabled1  Description of  opt-param="default"
                       root/enabled1   req-param
        root/enabled2  Description of
                       root/enabled2

    Package: vendor/package1

        Type              Description  Parameters
        package1/enabled

    Package: vendor/package2

        Type              Description  Parameters
        package2/enabled

The following types have duplicate definitions and are disabled:

    Package: vendor/root

        Type            Description  Parameters
        root/duplicate

    Package: vendor/package1

        Type                Description  Parameters
        package1/duplicate

    Package: vendor/package2

        Type                Description  Parameters
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

        $expected = <<<'EOF'
Type           Description                   Parameters
root/enabled1  Description of root/enabled1  opt-param="default"
                                             req-param
root/enabled2  Description of root/enabled2

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListEnabledTypesInPackage()
    {
        $args = self::$listCommand->parseArgs(new StringArgs('--enabled --package vendor/package1'));

        $expected = <<<'EOF'
Type              Description  Parameters
package1/enabled

EOF;

        $this->assertSame(0, $this->handler->handleList($args, $this->io));
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testListNoTypes()
    {
        $this->discoveryManager = $this->getMock('Puli\Manager\Api\Discovery\DiscoveryManager');
        $this->discoveryManager->expects($this->any())
            ->method('findTypeDescriptors')
            ->willReturn(array());
        $this->handler = new TypeCommandHandler($this->discoveryManager, $this->packages);

        $args = self::$listCommand->parseArgs(new StringArgs(''));

        $statusCode = $this->handler->handleList($args, $this->io);

        $expected = <<<'EOF'
No types defined. Use "puli type --define <name>" to define a type.

EOF;

        $this->assertSame(0, $statusCode);
        $this->assertSame($expected, $this->io->fetchOutput());
        $this->assertEmpty($this->io->fetchErrors());
    }

    public function testDefineType()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('my/type'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootTypeDescriptor')
            ->with(new BindingTypeDescriptor(new BindingType('my/type')));

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testDefineTypeWithDescription()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('my/type --description "The description"'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootTypeDescriptor')
            ->with(new BindingTypeDescriptor(new BindingType('my/type'), 'The description'));

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testDefineTypeWithRequiredParameter()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('my/type --param required'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootTypeDescriptor')
            ->with(new BindingTypeDescriptor(new BindingType('my/type', array(
                new BindingParameter('required', BindingParameter::REQUIRED),
            ))));

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testDefineTypeWithOptionalParameter()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('my/type --param optional=true'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootTypeDescriptor')
            ->with(new BindingTypeDescriptor(new BindingType('my/type', array(
                new BindingParameter('optional', BindingParameter::OPTIONAL, true),
            ))));

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testDefineTypeWithParameterDescription()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('my/type --param param --param-description param="The parameter description"'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootTypeDescriptor')
            ->with(new BindingTypeDescriptor(
                new BindingType('my/type', array(
                    new BindingParameter('param', BindingParameter::REQUIRED),
                )),
                null,
                array(
                    'param' => 'The parameter description',
                )
            ));

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testDefineTypeForce()
    {
        $args = self::$defineCommand->parseArgs(new StringArgs('--force my/type'));

        $this->discoveryManager->expects($this->once())
            ->method('addRootTypeDescriptor')
            ->with(new BindingTypeDescriptor(new BindingType('my/type')), DiscoveryManager::OVERRIDE);

        $this->assertSame(0, $this->handler->handleDefine($args));
    }

    public function testUpdateTypeDescription()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('my/type --description "New description"'));

        $typeDescriptor = new BindingTypeDescriptor(new BindingType('my/type'), 'Old description');
        $typeDescriptor->load($this->packages->getRootModule());

        $this->discoveryManager->expects($this->once())
            ->method('getRootTypeDescriptor')
            ->with('my/type')
            ->willReturn($typeDescriptor);

        $this->discoveryManager->expects($this->once())
            ->method('addRootTypeDescriptor')
            ->with(new BindingTypeDescriptor(new BindingType('my/type'), 'New description'), DiscoveryManager::OVERRIDE);

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateTypeOptionalParameterToRequired()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('my/type --param param'));

        $typeDescriptor = new BindingTypeDescriptor(
            new BindingType('my/type', array(
                new BindingParameter('param', BindingParameter::OPTIONAL, 'default'),
            )),
            null,
            array('param' => 'The description')
        );
        $typeDescriptor->load($this->packages->getRootModule());

        $this->discoveryManager->expects($this->once())
            ->method('getRootTypeDescriptor')
            ->with('my/type')
            ->willReturn($typeDescriptor);

        $this->discoveryManager->expects($this->once())
            ->method('addRootTypeDescriptor')
            ->with(new BindingTypeDescriptor(
                new BindingType('my/type', array(
                    new BindingParameter('param', BindingParameter::REQUIRED),
                )),
                null,
                array('param' => 'The description')
            ), DiscoveryManager::OVERRIDE);

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateTypeRequiredParameterToOptional()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('my/type --param param=foobar'));

        $typeDescriptor = new BindingTypeDescriptor(
            new BindingType('my/type', array(
                new BindingParameter('param', BindingParameter::REQUIRED),
            )),
            null,
            array('param' => 'The description')
        );
        $typeDescriptor->load($this->packages->getRootModule());

        $this->discoveryManager->expects($this->once())
            ->method('getRootTypeDescriptor')
            ->with('my/type')
            ->willReturn($typeDescriptor);

        $this->discoveryManager->expects($this->once())
            ->method('addRootTypeDescriptor')
            ->with(new BindingTypeDescriptor(
                new BindingType('my/type', array(
                    new BindingParameter('param', BindingParameter::OPTIONAL, 'foobar'),
                )),
                null,
                array('param' => 'The description')
            ), DiscoveryManager::OVERRIDE);

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateTypeChangeParameterDescription()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('my/type --param-description param="New description"'));

        $typeDescriptor = new BindingTypeDescriptor(
            new BindingType('my/type', array(
                new BindingParameter('param', BindingParameter::REQUIRED),
            )),
            null,
            array('param' => 'Old description')
        );
        $typeDescriptor->load($this->packages->getRootModule());

        $this->discoveryManager->expects($this->once())
            ->method('getRootTypeDescriptor')
            ->with('my/type')
            ->willReturn($typeDescriptor);

        $this->discoveryManager->expects($this->once())
            ->method('addRootTypeDescriptor')
            ->with(new BindingTypeDescriptor(
                new BindingType('my/type', array(
                    new BindingParameter('param', BindingParameter::REQUIRED),
                )),
                null,
                array('param' => 'New description')
            ), DiscoveryManager::OVERRIDE);

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    public function testUpdateTypeRemoveParameter()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('my/type --unset-param param2'));

        $typeDescriptor = new BindingTypeDescriptor(
            new BindingType('my/type', array(
                new BindingParameter('param1', BindingParameter::REQUIRED),
                new BindingParameter('param2', BindingParameter::OPTIONAL),
            ))
        );
        $typeDescriptor->load($this->packages->getRootModule());

        $this->discoveryManager->expects($this->once())
            ->method('getRootTypeDescriptor')
            ->with('my/type')
            ->willReturn($typeDescriptor);

        $this->discoveryManager->expects($this->once())
            ->method('addRootTypeDescriptor')
            ->with(new BindingTypeDescriptor(
                new BindingType('my/type', array(
                    new BindingParameter('param1', BindingParameter::REQUIRED),
                ))
            ), DiscoveryManager::OVERRIDE);

        $this->assertSame(0, $this->handler->handleUpdate($args));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testUpdateTypeFailsIfNoChanges()
    {
        $args = self::$updateCommand->parseArgs(new StringArgs('my/type'));

        $typeDescriptor = new BindingTypeDescriptor(new BindingType('my/type'));
        $typeDescriptor->load($this->packages->getRootModule());

        $this->discoveryManager->expects($this->once())
            ->method('getRootTypeDescriptor')
            ->with('my/type')
            ->willReturn($typeDescriptor);

        $this->discoveryManager->expects($this->never())
            ->method('addRootTypeDescriptor');

        $this->handler->handleUpdate($args);
    }

    public function testDeleteType()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('my/type'));

        $this->discoveryManager->expects($this->once())
            ->method('hasRootTypeDescriptor')
            ->with('my/type')
            ->willReturn(true);

        $this->discoveryManager->expects($this->once())
            ->method('removeRootTypeDescriptor')
            ->with('my/type');

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The type "my/type" does not exist in the package "vendor/root".
     */
    public function testDeleteTypeFailsIfNotFound()
    {
        $args = self::$deleteCommand->parseArgs(new StringArgs('my/type'));

        $this->discoveryManager->expects($this->once())
            ->method('hasRootTypeDescriptor')
            ->with('my/type')
            ->willReturn(false);

        $this->discoveryManager->expects($this->never())
            ->method('removeRootTypeDescriptor');

        $this->assertSame(0, $this->handler->handleDelete($args));
    }

    private function packageAndState($moduleName, $state)
    {
        return Expr::method('getContainingModule', Expr::method('getName', Expr::same($moduleName)))
            ->andMethod('getState', Expr::same($state));
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
