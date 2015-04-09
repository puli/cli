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
use RuntimeException;
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
        $bindingParams = array();
        $paramDescriptions = array();

        $this->parsesParamDescriptions($args, $paramDescriptions);
        $this->parseParams($args, $paramDescriptions, $bindingParams);

        $this->discoveryManager->addRootBindingType(new BindingTypeDescriptor(
            $args->getArgument('name'),
            $args->getOption('description'),
            $bindingParams
        ), $flags);

        return 0;
    }

    /**
     * Handles the "type update" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleUpdate(Args $args)
    {
        $name = $args->getArgument('name');
        $typeToUpdate = $this->discoveryManager->getRootBindingType($name);
        $bindingParams = $typeToUpdate->getParameters();
        $description = $typeToUpdate->getDescription();
        $paramDescriptions = array();

        // Collect existing parameter descriptions
        foreach ($bindingParams as $parameter) {
            $paramDescriptions[$parameter->getName()] = $parameter->getDescription();
        }

        $this->parsesParamDescriptions($args, $paramDescriptions);
        $this->parseParams($args, $paramDescriptions, $bindingParams);
        $this->updateParamDescriptions($paramDescriptions, $bindingParams);
        $this->parseUnsetParams($args, $bindingParams);

        if ($args->isOptionSet('description')) {
            $description = $args->getOption('description');
        }

        $updatedType = new BindingTypeDescriptor($name, $description, $bindingParams);

        if ($this->typesEqual($typeToUpdate, $updatedType)) {
            throw new RuntimeException('Nothing to update.');
        }

        $this->discoveryManager->addRootBindingType($updatedType, DiscoveryManager::OVERRIDE);

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

    private function parsesParamDescriptions(Args $args, array &$paramDescriptions)
    {
        foreach ($args->getOption('param-description') as $paramDescription) {
            $pos = strpos($paramDescription, '=');

            if (false === $pos) {
                throw new RuntimeException(sprintf(
                    'The "--param-description" option expects a parameter in '.
                    'the form "key=value". Got: "%s"',
                    $paramDescription
                ));
            }

            $key = substr($paramDescription, 0, $pos);
            $paramDescriptions[$key] = StringUtil::parseValue(substr($paramDescription, $pos + 1));
        }
    }

    private function parseParams(Args $args, array $paramDescriptions, array &$bindingParams)
    {
        foreach ($args->getOption('param') as $parameter) {
            // Optional parameter with default value
            if (false !== ($pos = strpos($parameter, '='))) {
                $key = substr($parameter, 0, $pos);

                $bindingParams[$key] = new BindingParameterDescriptor(
                    $key,
                    BindingParameterDescriptor::OPTIONAL,
                    StringUtil::parseValue(substr($parameter, $pos + 1)),
                    isset($paramDescriptions[$key]) ? $paramDescriptions[$key] : null
                );

                continue;
            }

            // Required parameter
            $bindingParams[$parameter] = new BindingParameterDescriptor(
                $parameter,
                BindingParameterDescriptor::REQUIRED,
                null,
                isset($paramDescriptions[$parameter]) ? $paramDescriptions[$parameter] : null
            );
        }
    }

    /**
     * @param string[]                     $paramDescriptions
     * @param BindingParameterDescriptor[] $bindingParams
     */
    private function updateParamDescriptions(array $paramDescriptions, array &$bindingParams)
    {
        foreach ($bindingParams as $parameterName => $parameter) {
            if (!isset($paramDescriptions[$parameterName])) {
                continue;
            }

            $description = $paramDescriptions[$parameterName];

            if ($description === $parameter->getDescription()) {
                continue;
            }

            $bindingParams[$parameterName] = new BindingParameterDescriptor(
                $parameterName,
                $parameter->getFlags(),
                $parameter->getDefaultValue(),
                $description
            );
        }
    }

    private function parseUnsetParams(Args $args, array &$bindingParams)
    {
        foreach ($args->getOption('unset-param') as $parameter) {
            unset($bindingParams[$parameter]);
        }
    }

    private function typesEqual(BindingTypeDescriptor $type1, BindingTypeDescriptor $type2)
    {
        if ($type1->getName() !== $type2->getName()) {
            return false;
        }

        if ($type1->getDescription() !== $type2->getDescription()) {
            return false;
        }

        if ($type1->getParameters() != $type2->getParameters()) {
            return false;
        }

        return true;
    }
}
