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
use Puli\Manager\Api\Discovery\BindingParameterDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeState;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Package\PackageCollection;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Console\UI\Style\TableStyle;
use Webmozart\Expression\Expr;

/**
 * Handles the "type" command.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TypeCommandHandler
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
     * Handles the "type list" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handleList(Args $args, IO $io)
    {
        $packageNames = ArgsUtil::getPackageNames($args, $this->packages);
        $states = $this->getBindingTypeStates($args);

        $printStates = count($states) > 1;
        $printPackageName = count($packageNames) > 1;
        $printHeaders = $printStates || $printPackageName;
        $printAdvice = false;

        foreach ($states as $state) {
            $statePrinted = !$printStates;

            foreach ($packageNames as $packageName) {
                $expr = Expr::same(BindingTypeDescriptor::CONTAINING_PACKAGE, $packageName)
                    ->andSame(BindingTypeDescriptor::STATE, $state);

                $bindingTypes = $this->discoveryManager->findBindingTypes($expr);

                if (!$bindingTypes) {
                    continue;
                }

                if (!$statePrinted) {
                    $this->printBindingTypeState($io, $state);
                    $statePrinted = true;

                    // Only print the advice if at least one type was printed
                    $printAdvice = true;
                }

                if ($printPackageName) {
                    $prefix = $printStates ? '    ' : '';
                    $io->writeLine("$prefix<b>$packageName</b>");
                }

                $styleTag = BindingTypeState::ENABLED === $state ? null : 'bad';

                $this->printTypeTable($io, $bindingTypes, $styleTag, $printStates);

                if ($printHeaders) {
                    $io->writeLine('');
                }
            }
        }

        if ($printAdvice) {
            $io->writeLine('Use "puli binding add <resource> <type>" to bind a resource to a type.');
        }

        return 0;
    }

    /**
     * Handles the "type define" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleDefine(Args $args)
    {
        $flags = $args->isOptionSet('force') ? DiscoveryManager::OVERRIDE : 0;
        $descriptions = $args->getOption('description');
        $bindingParams = array();

        // The first description is for the type
        $description = $descriptions ? array_shift($descriptions) : null;

        foreach ($args->getOption('param') as $parameter) {
            // Subsequent descriptions are for the parameters
            $paramDescription = $descriptions ? array_shift($descriptions) : null;

            // Optional parameter with default value
            if (false !== ($pos = strpos($parameter, '='))) {
                $bindingParams[] = new BindingParameterDescriptor(
                    substr($parameter, 0, $pos),
                    BindingParameterDescriptor::OPTIONAL,
                    StringUtil::parseValue(substr($parameter, $pos + 1)),
                    $paramDescription
                );

                continue;
            }

            // Required parameter
            $bindingParams[] = new BindingParameterDescriptor(
                $parameter,
                BindingParameterDescriptor::REQUIRED,
                null,
                $paramDescription
            );
        }

        $this->discoveryManager->addRootBindingType(new BindingTypeDescriptor(
            $args->getArgument('name'),
            $description,
            $bindingParams
        ), $flags);

        return 0;
    }

    /**
     * Handles the "type remove" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleRemove(Args $args)
    {
        $this->discoveryManager->removeRootBindingType($args->getArgument('name'));

        return 0;
    }

    /**
     * Returns the binding type states selected in the console arguments.
     *
     * @param Args $args The console arguments.
     *
     * @return int[] A list of {@link BindingTypeState} constants.
     */
    private function getBindingTypeStates(Args $args)
    {
        $states = array();

        if ($args->isOptionSet('enabled')) {
            $states[] = BindingTypeState::ENABLED;
        }

        if ($args->isOptionSet('duplicate')) {
            $states[] = BindingTypeState::DUPLICATE;
        }

        return $states ?: BindingTypeState::all();
    }

    /**
     * Prints the binding types in a table.
     *
     * @param IO                      $io       The I/O.
     * @param BindingTypeDescriptor[] $types    The binding types to print.
     * @param string                  $styleTag The tag used to style the output
     * @param bool                    $indent   Whether to indent the output.
     */
    private function printTypeTable(IO $io, array $types, $styleTag = null, $indent = false)
    {
        $table = new Table(TableStyle::borderless());

        $paramTag = $styleTag ?: 'c1';
        $typeTag = $styleTag ?: 'u';

        foreach ($types as $type) {
            $parameters = array();

            foreach ($type->getParameters() as $parameter) {
                $parameters[] = $parameter->isRequired()
                    ? $parameter->getName()
                    : $parameter->getName().'='.StringUtil::formatValue($parameter->getDefaultValue());
            }

            $paramString = $parameters ? "<$paramTag>(".implode(', ', $parameters).")</$paramTag>" : '';
            $description = $type->getDescription();

            if ($description && $paramString) {
                $paramString = ' '.$paramString;
            }

            if ($styleTag) {
                $description = "<$styleTag>$description</$styleTag>";
            }

            $table->addRow(array(
                "<$typeTag>".$type->getName()."</$typeTag>",
                ltrim($description.$paramString)
            ));
        }

        $table->render($io, $indent ? 4 : 0);
    }

    /**
     * Prints the heading for a binding type state.
     *
     * @param IO  $io        The I/O.
     * @param int $typeState The {@link BindingTypeState} constant.
     */
    private function printBindingTypeState(IO $io, $typeState)
    {
        switch ($typeState) {
            case BindingTypeState::ENABLED:
                $io->writeLine('Enabled binding types:');
                $io->writeLine('');
                return;
            case BindingTypeState::DUPLICATE:
                $io->writeLine('The following types have duplicate definitions and are disabled:');
                $io->writeLine('');
                return;
        }
    }

}
