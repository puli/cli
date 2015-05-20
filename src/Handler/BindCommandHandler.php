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
                $expr = Expr::same($packageName, BindingDescriptor::CONTAINING_PACKAGE)
                    ->andSame($bindingState, BindingDescriptor::STATE);

                $bindings = $this->discoveryManager->findBindings($expr);

                if (!$bindings) {
                    continue;
                }

                if (!$bindingStatePrinted) {
                    $this->printBindingStateHeader($io, $bindingState);
                    $bindingStatePrinted = true;
                }

                if ($printPackageName) {
                    $prefix = $printBindingState ? '    ' : '';
                    $io->writeLine("<b>{$prefix}Package: $packageName</b>");
                    $io->writeLine('');
                }

                $this->printBindingTable($io, $bindings, $indentation, BindingState::ENABLED === $bindingState);

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
            ? DiscoveryManager::OVERRIDE |DiscoveryManager::IGNORE_TYPE_NOT_FOUND
                | DiscoveryManager::IGNORE_TYPE_NOT_ENABLED
            : 0;

        $bindingParams = array();

        $this->parseParams($args, $bindingParams);

        $this->discoveryManager->addRootBinding(new BindingDescriptor(
            Path::makeAbsolute($args->getArgument('query'), $this->currentPath),
            $args->getArgument('type'),
            $bindingParams,
            $args->getOption('language')
        ), $flags);

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

        $bindingToUpdate = $this->getBindingByUuidPrefix($args->getArgument('uuid'));

        if (!$bindingToUpdate->getContainingPackage() instanceof RootPackage) {
            throw new RuntimeException(sprintf(
                'Can only update bindings in the package "%s".',
                $this->packages->getRootPackageName()
            ));
        }

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

        $updatedBinding = new BindingDescriptor(
            Path::makeAbsolute($query, $this->currentPath),
            $typeName,
            $bindingParams,
            $language,
            $bindingToUpdate->getUuid()
        );

        if ($this->bindingsEqual($bindingToUpdate, $updatedBinding)) {
            throw new RuntimeException('Nothing to update.');
        }

        $this->discoveryManager->addRootBinding($updatedBinding, $flags);

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

        $this->discoveryManager->removeRootBinding($bindingToRemove->getUuid());

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

        $this->discoveryManager->enableBinding($bindingToEnable->getUuid());

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

        $this->discoveryManager->disableBinding($bindingToDisable->getUuid());


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
        $states = array();

        if ($args->isOptionSet('enabled')) {
            $states[] = BindingState::ENABLED;
        }

        if ($args->isOptionSet('disabled')) {
            $states[] = BindingState::DISABLED;
        }

        if ($args->isOptionSet('undecided')) {
            $states[] = BindingState::UNDECIDED;
        }

        if ($args->isOptionSet('type-not-found')) {
            $states[] = BindingState::TYPE_NOT_FOUND;
        }

        if ($args->isOptionSet('type-not-enabled')) {
            $states[] = BindingState::TYPE_NOT_ENABLED;
        }

        if ($args->isOptionSet('invalid')) {
            $states[] = BindingState::INVALID;
        }

        return $states ?: BindingState::all();
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
        $queryTag = $enabled ? 'c1' : 'bad';
        $typeTag = $enabled ? 'u' : 'bad';

        foreach ($descriptors as $descriptor) {
            $parameters = array();

            foreach ($descriptor->getParameterValues() as $parameterName => $value) {
                $parameters[] = $parameterName.'='.StringUtil::formatValue($value);
            }

            $uuid = substr($descriptor->getUuid(), 0, 6);

            if (!$enabled) {
                $uuid = "<bad>$uuid</bad>";
            }

            if ($parameters) {
                // \xc2\xa0 is a non-breaking space
                $paramString = " <$paramTag>(".implode(",\xc2\xa0", $parameters).")</$paramTag>";
            } else {
                $paramString = '';
            }

            $table->addRow(array(
                $uuid,
                "<$queryTag>{$descriptor->getQuery()}</$queryTag>",
                "<$typeTag>{$descriptor->getTypeName()}</$typeTag>".$paramString
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
                $io->writeLine('The following bindings are currently enabled in your application:');
                $io->writeLine('');
                return;
            case BindingState::DISABLED:
                $io->writeLine('The following bindings are disabled:');
                $io->writeLine(' (use "puli bind --enable <uuid>" to enable)');
                $io->writeLine('');
                return;
            case BindingState::UNDECIDED:
                $io->writeLine('Bindings that are neither enabled nor disabled:');
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
        $expr = Expr::startsWith($uuidPrefix, BindingDescriptor::UUID);
        $bindings = $this->discoveryManager->findBindings($expr);

        if (0 === count($bindings)) {
            throw new RuntimeException(sprintf('The binding "%s" does not exist.', $uuidPrefix));
        }

        if (count($bindings) > 1) {
            throw new RuntimeException(sprintf(
                'More than one binding matches the UUID prefix "%s".',
                $uuidPrefix
            ));
        }

        return reset($bindings);
    }

    private function bindingsEqual(BindingDescriptor $binding1, BindingDescriptor $binding2)
    {
        if ($binding1->getUuid() !== $binding2->getUuid()) {
            return false;
        }

        if ($binding1->getTypeName() !== $binding2->getTypeName()) {
            return false;
        }

        if ($binding1->getQuery() !== $binding2->getQuery()) {
            return false;
        }

        if ($binding1->getLanguage() !== $binding2->getLanguage()) {
            return false;
        }

        if ($binding1->getParameterValues() !== $binding2->getParameterValues()) {
            return false;
        }

        return true;
    }
}
