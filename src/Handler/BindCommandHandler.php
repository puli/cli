<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Handler;

use Puli\Cli\Style\PuliTableStyle;
use Puli\Cli\Util\ArgsUtil;
use Puli\Cli\Util\StringUtil;
use Puli\Discovery\Binding\ClassBinding;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingState;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\RootPackage;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Expression\Expr;
use Webmozart\PathUtil\Path;

/**
 * Handles the "bind" command.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindCommandHandler
{
    /**
     * @var DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var string
     */
    private $currentPath = '/';

    /**
     * Creates the handler.
     *
     * @param DiscoveryManager  $discoveryManager The discovery manager.
     * @param PackageCollection $packages         The loaded packages.
     */
    public function __construct(DiscoveryManager $discoveryManager, PackageCollection $packages)
    {
        $this->discoveryManager = $discoveryManager;
        $this->packages = $packages;
    }

    /**
     * Handles the "bind --list" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handleList(Args $args, IO $io)
    {
        $packageNames = ArgsUtil::getPackageNames($args, $this->packages);
        $bindingStates = $this->getBindingStates($args);

        $printBindingState = count($bindingStates) > 1;
        $printPackageName = count($packageNames) > 1;
        $printHeaders = $printBindingState || $printPackageName;
        $indentation = $printBindingState && $printPackageName ? 8
            : ($printBindingState || $printPackageName ? 4 : 0);

        foreach ($bindingStates as $bindingState) {
            $bindingStatePrinted = !$printBindingState;

            foreach ($packageNames as $packageName) {
                $expr = Expr::method('getContainingPackage', Expr::method('getName', Expr::same($packageName)))
                    ->andMethod('getState', Expr::same($bindingState));

                $descriptors = $this->discoveryManager->findBindingDescriptors($expr);

                if (empty($descriptors)) {
                    continue;
                }

                if (!$bindingStatePrinted) {
                    $this->printBindingStateHeader($io, $bindingState);
                    $bindingStatePrinted = true;
                }

                if ($printPackageName) {
                    $prefix = $printBindingState ? '    ' : '';
                    $io->writeLine(sprintf('%sPackage: %s', $prefix, $packageName));
                    $io->writeLine('');
                }

                $this->printBindingTable($io, $descriptors, $indentation, BindingState::ENABLED === $bindingState);

                if ($printHeaders) {
                    $io->writeLine('');
                }
            }
        }

        return 0;
    }

    /**
     * Handles the "bind <query> <type>" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleAdd(Args $args)
    {
        $flags = $args->isOptionSet('force')
            ? DiscoveryManager::OVERRIDE | DiscoveryManager::IGNORE_TYPE_NOT_FOUND
                | DiscoveryManager::IGNORE_TYPE_NOT_ENABLED
            : 0;

        $bindingParams = array();
        $artifact = $args->getArgument('artifact');

        $this->parseParams($args, $bindingParams);

        if (false !== strpos($artifact, '\\') || $args->isOptionSet('class')) {
            $binding = new ClassBinding(
                $artifact,
                $args->getArgument('type'),
                $bindingParams
            );
        } else {
            $binding = new ResourceBinding(
                Path::makeAbsolute($artifact, $this->currentPath),
                $args->getArgument('type'),
                $bindingParams,
                $args->getOption('language')
            );
        }

        $this->discoveryManager->addRootBindingDescriptor(new BindingDescriptor($binding), $flags);

        return 0;
    }

    /**
     * Handles the "bind --update <uuid>" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleUpdate(Args $args)
    {
        $flags = $args->isOptionSet('force')
            ? DiscoveryManager::OVERRIDE | DiscoveryManager::IGNORE_TYPE_NOT_FOUND
                | DiscoveryManager::IGNORE_TYPE_NOT_ENABLED
            : DiscoveryManager::OVERRIDE;

        $descriptorToUpdate = $this->getBindingByUuidPrefix($args->getArgument('uuid'));
        $bindingToUpdate = $descriptorToUpdate->getBinding();

        if (!$descriptorToUpdate->getContainingPackage() instanceof RootPackage) {
            throw new RuntimeException(sprintf(
                'Can only update bindings in the package "%s".',
                $this->packages->getRootPackageName()
            ));
        }

        if ($bindingToUpdate instanceof ResourceBinding) {
            $updatedBinding = $this->getUpdatedResourceBinding($args, $bindingToUpdate);
        } elseif ($bindingToUpdate instanceof ClassBinding) {
            $updatedBinding = $this->getUpdatedClassBinding($args, $bindingToUpdate);
        } else {
            throw new RuntimeException(sprintf(
                'Cannot update bindings of type %s.',
                get_class($bindingToUpdate)
            ));
        }

        $updatedDescriptor = new BindingDescriptor($updatedBinding);

        if ($this->bindingsEqual($descriptorToUpdate, $updatedDescriptor)) {
            throw new RuntimeException('Nothing to update.');
        }

        $this->discoveryManager->addRootBindingDescriptor($updatedDescriptor, $flags);

        return 0;
    }

    /**
     * Handles the "bind --delete" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleDelete(Args $args)
    {
        $bindingToRemove = $this->getBindingByUuidPrefix($args->getArgument('uuid'));

        if (!$bindingToRemove->getContainingPackage() instanceof RootPackage) {
            throw new RuntimeException(sprintf(
                'Can only delete bindings from the package "%s".',
                $this->packages->getRootPackageName()
            ));
        }

        $this->discoveryManager->removeRootBindingDescriptor($bindingToRemove->getUuid());

        return 0;
    }

    /**
     * Handles the "bind --enable" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleEnable(Args $args)
    {
        $bindingToEnable = $this->getBindingByUuidPrefix($args->getArgument('uuid'));

        if ($bindingToEnable->getContainingPackage() instanceof RootPackage) {
            throw new RuntimeException(sprintf(
                'Cannot enable bindings in the package "%s".',
                $bindingToEnable->getContainingPackage()->getName()
            ));
        }

        $this->discoveryManager->enableBindingDescriptor($bindingToEnable->getUuid());

        return 0;
    }

    /**
     * Handles the "bind --disable" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleDisable(Args $args)
    {
        $bindingToDisable = $this->getBindingByUuidPrefix($args->getArgument('uuid'));

        if ($bindingToDisable->getContainingPackage() instanceof RootPackage) {
            throw new RuntimeException(sprintf(
                'Cannot disable bindings in the package "%s".',
                $bindingToDisable->getContainingPackage()->getName()
            ));
        }

        $this->discoveryManager->disableBindingDescriptor($bindingToDisable->getUuid());

        return 0;
    }

    /**
     * Returns the binding states selected in the console arguments.
     *
     * @param Args $args The console arguments.
     *
     * @return int[] The selected {@link BindingState} constants.
     */
    private function getBindingStates(Args $args)
    {
        $states = array(
            BindingState::ENABLED => 'enabled',
            BindingState::DISABLED => 'disabled',
            BindingState::TYPE_NOT_FOUND => 'type-not-found',
            BindingState::TYPE_NOT_ENABLED => 'type-not-enabled',
            BindingState::INVALID => 'invalid',
        );

        $states = array_filter($states, function ($option) use ($args) {
            return $args->isOptionSet($option);
        });

        return array_keys($states) ?: BindingState::all();
    }

    /**
     * Prints a list of binding descriptors.
     *
     * @param IO                  $io          The I/O.
     * @param BindingDescriptor[] $descriptors The binding descriptors.
     * @param int                 $indentation The number of spaces to indent.
     * @param bool                $enabled     Whether the binding descriptors
     *                                         are enabled. If not, the output
     *                                         is printed in red.
     */
    private function printBindingTable(IO $io, array $descriptors, $indentation = 0, $enabled = true)
    {
        $table = new Table(PuliTableStyle::borderless());

        $table->setHeaderRow(array('UUID', 'Glob', 'Type'));

        $paramTag = $enabled ? 'c1' : 'bad';
        $artifactTag = $enabled ? 'c1' : 'bad';
        $typeTag = $enabled ? 'u' : 'bad';

        foreach ($descriptors as $descriptor) {
            $parameters = array();
            $binding = $descriptor->getBinding();

            foreach ($binding->getParameterValues() as $parameterName => $parameterValue) {
                $parameters[] = $parameterName.'='.StringUtil::formatValue($parameterValue);
            }

            $uuid = substr($descriptor->getUuid(), 0, 6);

            if (!$enabled) {
                $uuid = sprintf('<bad>%s</bad>', $uuid);
            }

            $paramString = '';

            if (!empty($parameters)) {
                // \xc2\xa0 is a non-breaking space
                $paramString = sprintf(
                    ' <%s>(%s)</%s>',
                    $paramTag,
                    implode(",\xc2\xa0", $parameters),
                    $paramTag
                );
            }

            if ($binding instanceof ResourceBinding) {
                $artifact = $binding->getQuery();
            } elseif ($binding instanceof ClassBinding) {
                $artifact = StringUtil::getShortClassName($binding->getClassName());
            } else {
                continue;
            }

            $typeString = StringUtil::getShortClassName($binding->getTypeName());

            $table->addRow(array(
                $uuid,
                sprintf('<%s>%s</%s>', $artifactTag, $artifact, $artifactTag),
                sprintf('<%s>%s</%s>%s', $typeTag, $typeString, $typeTag, $paramString),
            ));
        }

        $table->render($io, $indentation);
    }

    /**
     * Prints the header for a binding state.
     *
     * @param IO  $io           The I/O.
     * @param int $bindingState The {@link BindingState} constant.
     */
    private function printBindingStateHeader(IO $io, $bindingState)
    {
        switch ($bindingState) {
            case BindingState::ENABLED:
                $io->writeLine('The following bindings are currently enabled:');
                $io->writeLine('');

                return;
            case BindingState::DISABLED:
                $io->writeLine('The following bindings are disabled:');
                $io->writeLine(' (use "puli bind --enable <uuid>" to enable)');
                $io->writeLine('');

                return;
            case BindingState::TYPE_NOT_FOUND:
                $io->writeLine('The types of the following bindings could not be found:');
                $io->writeLine(' (install or create their type definitions to enable)');
                $io->writeLine('');

                return;
            case BindingState::TYPE_NOT_ENABLED:
                $io->writeLine('The types of the following bindings are not enabled:');
                $io->writeLine(' (remove the duplicate type definitions to enable)');
                $io->writeLine('');

                return;
            case BindingState::INVALID:
                $io->writeLine('The following bindings have invalid parameters:');
                $io->writeLine(' (remove the binding and add again with correct parameters)');
                $io->writeLine('');

                return;
        }
    }

    private function parseParams(Args $args, array &$bindingParams)
    {
        foreach ($args->getOption('param') as $parameter) {
            $pos = strpos($parameter, '=');

            if (false === $pos) {
                throw new RuntimeException(sprintf(
                    'The "--param" option expects a parameter in the form '.
                    '"key=value". Got: "%s"',
                    $parameter
                ));
            }

            $key = substr($parameter, 0, $pos);
            $value = StringUtil::parseValue(substr($parameter, $pos + 1));

            $bindingParams[$key] = $value;
        }
    }

    private function unsetParams(Args $args, array &$bindingParams)
    {
        foreach ($args->getOption('unset-param') as $parameter) {
            unset($bindingParams[$parameter]);
        }
    }

    /**
     * @param string $uuidPrefix
     *
     * @return BindingDescriptor
     */
    private function getBindingByUuidPrefix($uuidPrefix)
    {
        $expr = Expr::method('getUuid', Expr::startsWith($uuidPrefix));
        $descriptors = $this->discoveryManager->findBindingDescriptors($expr);

        if (0 === count($descriptors)) {
            throw new RuntimeException(sprintf('The binding "%s" does not exist.', $uuidPrefix));
        }

        if (count($descriptors) > 1) {
            throw new RuntimeException(sprintf(
                'More than one binding matches the UUID prefix "%s".',
                $uuidPrefix
            ));
        }

        return reset($descriptors);
    }

    private function bindingsEqual(BindingDescriptor $descriptor1, BindingDescriptor $descriptor2)
    {
        return $descriptor1->getBinding() == $descriptor2->getBinding();
    }

    /**
     * @param Args            $args
     * @param ResourceBinding $bindingToUpdate
     *
     * @return ResourceBinding
     */
    private function getUpdatedResourceBinding(Args $args, ResourceBinding $bindingToUpdate)
    {
        $query = $bindingToUpdate->getQuery();
        $typeName = $bindingToUpdate->getTypeName();
        $language = $bindingToUpdate->getLanguage();
        $bindingParams = $bindingToUpdate->getParameterValues();

        if ($args->isOptionSet('query')) {
            $query = $args->getOption('query');
        }

        if ($args->isOptionSet('type')) {
            $typeName = $args->getOption('type');
        }

        if ($args->isOptionSet('language')) {
            $language = $args->getOption('language');
        }

        $this->parseParams($args, $bindingParams);
        $this->unsetParams($args, $bindingParams);

        return new ResourceBinding(
            Path::makeAbsolute($query, $this->currentPath),
            $typeName,
            $bindingParams,
            $language,
            $bindingToUpdate->getUuid()
        );
    }

    /**
     * @param Args         $args
     * @param ClassBinding $bindingToUpdate
     *
     * @return ClassBinding
     */
    private function getUpdatedClassBinding(Args $args, ClassBinding $bindingToUpdate)
    {
        $className = $bindingToUpdate->getClassName();
        $typeName = $bindingToUpdate->getTypeName();
        $bindingParams = $bindingToUpdate->getParameterValues();

        if ($args->isOptionSet('class')) {
            $className = $args->getOption('class');
        }

        if ($args->isOptionSet('type')) {
            $typeName = $args->getOption('type');
        }

        $this->parseParams($args, $bindingParams);
        $this->unsetParams($args, $bindingParams);

        return new ClassBinding(
            $className,
            $typeName,
            $bindingParams,
            $bindingToUpdate->getUuid()
        );
    }
}
