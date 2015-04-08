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

use Puli\Cli\Util\ArgsUtil;
use Puli\Cli\Util\StringUtil;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingState;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Package\PackageCollection;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Console\UI\Style\TableStyle;
use Webmozart\Expression\Expr;
use Webmozart\PathUtil\Path;

/**
 * Handles the "binding" command.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingCommandHandler
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
     * Handles the "bind -l" command.
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

        foreach ($bindingStates as $bindingState) {
            $bindingStatePrinted = !$printBindingState;

            foreach ($packageNames as $packageName) {
                $expr = Expr::same(BindingDescriptor::CONTAINING_PACKAGE, $packageName)
                    ->andSame(BindingDescriptor::STATE, $bindingState);

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
                    $io->writeLine("<b>$prefix$packageName</b>");
                }

                $this->printBindingTable($io, $bindings, $printBindingState, BindingState::ENABLED === $bindingState);

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
    public function handleSave(Args $args)
    {
        $flags = $args->isOptionSet('force') ? DiscoveryManager::NO_TYPE_CHECK : 0;

        $bindingParams = array();

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

        $this->discoveryManager->addBinding(new BindingDescriptor(
            Path::makeAbsolute($args->getArgument('query'), $this->currentPath),
            $args->getArgument('type'),
            $bindingParams,
            $args->getOption('language')
        ), $flags);

        return 0;
    }

    /**
     * Handles the "bind -d" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleRemove(Args $args)
    {
        $uuid = $args->getArgument('uuid');
        $rootPackageName = $this->packages->getRootPackageName();
        $uuidExpr = Expr::startsWith(BindingDescriptor::UUID, $uuid);
        $uuidInRootExpr = $uuidExpr->andSame(BindingDescriptor::CONTAINING_PACKAGE, $rootPackageName);
        $bindings = $this->discoveryManager->findBindings($uuidInRootExpr);

        if (0 === count($bindings)) {
            $nonRootBindings = $this->discoveryManager->findBindings($uuidExpr);

            if (count($nonRootBindings) > 0) {
                throw new RuntimeException('Can only delete bindings from the root package.');
            }

            throw new RuntimeException(sprintf(
                'The binding "%s" does not exist.',
                $uuid
            ));
        }

        if (count($bindings) > 1) {
            throw new RuntimeException(sprintf(
                'More than one binding matches the UUID prefix "%s".',
                $uuid
            ));
        }

        $bindingToRemove = reset($bindings);
        $this->discoveryManager->removeBinding($bindingToRemove->getUuid());

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
        $uuid = $args->getArgument('uuid');
        $expr = Expr::startsWith(BindingDescriptor::UUID, $uuid);
        $bindings = $this->discoveryManager->findBindings($expr);

        if (0 === count($bindings)) {
            throw new RuntimeException(sprintf(
                'The binding "%s" does not exist.',
                $uuid
            ));
        }

        foreach ($bindings as $binding) {
            $this->discoveryManager->enableBinding($binding->getUuid());
        }

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
        $uuid = $args->getArgument('uuid');
        $expr = Expr::startsWith(BindingDescriptor::UUID, $uuid);
        $bindings = $this->discoveryManager->findBindings($expr);

        if (0 === count($bindings)) {
            throw new RuntimeException(sprintf(
                'The binding "%s" does not exist.',
                $uuid
            ));
        }

        foreach ($bindings as $binding) {
            $this->discoveryManager->disableBinding($binding->getUuid());
        }

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

        if ($args->isOptionSet('type-not-loaded')) {
            $states[] = BindingState::TYPE_NOT_LOADED;
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
     * @param bool                $indent      Whether to indent the output.
     * @param bool                $enabled     Whether the binding descriptors
     *                                         are enabled. If not, the output
     *                                         is printed in red.
     */
    private function printBindingTable(IO $io, array $descriptors, $indent = false, $enabled = true)
    {
        $table = new Table(TableStyle::borderless());

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
                "$uuid <$queryTag>{$descriptor->getQuery()}</$queryTag>",
                "<$typeTag>{$descriptor->getTypeName()}</$typeTag>".$paramString
            ));
        }

        $table->render($io, $indent ? 4 : 0);
    }

    /**
     * Prints the header fora  binding state.
     *
     * @param IO  $io           The I/O.
     * @param int $bindingState The {@link BindingState} constant.
     */
    private function printBindingStateHeader(IO $io, $bindingState)
    {
        switch ($bindingState) {
            case BindingState::ENABLED:
                $io->writeLine('Enabled bindings:');
                $io->writeLine('');
                return;
            case BindingState::DISABLED:
                $io->writeLine('Disabled bindings:');
                $io->writeLine(' (use "puli bind --enable <uuid>" to enable)');
                $io->writeLine('');
                return;
            case BindingState::UNDECIDED:
                $io->writeLine('Bindings that are neither enabled nor disabled:');
                $io->writeLine(' (use "puli bind --enable <uuid>" to enable)');
                $io->writeLine('');
                return;
            case BindingState::TYPE_NOT_LOADED:
                $io->writeLine('The types of the following bindings are not loaded:');
                $io->writeLine(' (install or fix their type definitions to enable)');
                $io->writeLine('');
                return;
            case BindingState::INVALID:
                $io->writeLine('The following bindings have invalid parameters:');
                $io->writeLine(' (remove the binding and add again with correct parameters)');
                $io->writeLine('');
                return;
        }
    }
}
