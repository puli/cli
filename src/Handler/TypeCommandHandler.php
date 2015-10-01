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
use Puli\Discovery\Api\Type\BindingParameter;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeState;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Package\PackageCollection;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Expression\Expr;

/**
 * Handles the "puli type" command.
 *
 * @since  1.0
 *
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
     * Handles the "puli type --list" command.
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
        $indentation = $printStates && $printPackageName ? 8
            : ($printStates || $printPackageName ? 4 : 0);

        foreach ($states as $state) {
            $statePrinted = !$printStates;

            foreach ($packageNames as $packageName) {
                $expr = Expr::method('getContainingPackage', Expr::method('getName', Expr::same($packageName)))
                    ->andMethod('getState', Expr::same($state));

                $bindingTypes = $this->discoveryManager->findTypeDescriptors($expr);

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
                    $io->writeLine("{$prefix}Package: $packageName");
                    $io->writeLine('');
                }

                $styleTag = BindingTypeState::ENABLED === $state ? null : 'bad';

                $this->printTypeTable($io, $bindingTypes, $styleTag, $indentation);

                if ($printHeaders) {
                    $io->writeLine('');
                }
            }
        }

        if ($printAdvice) {
            $io->writeLine('Use "puli bind <resource> <type>" to bind a resource to a type.');
        }

        return 0;
    }

    /**
     * Handles the "puli type --define" command.
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

        $this->parseParamDescriptions($args, $paramDescriptions);
        $this->parseParams($args, $bindingParams);

        $this->discoveryManager->addRootTypeDescriptor(new BindingTypeDescriptor(
            new BindingType($args->getArgument('name'), $bindingParams),
            $args->getOption('description'),
            $paramDescriptions
        ), $flags);

        return 0;
    }

    /**
     * Handles the "puli type --update" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleUpdate(Args $args)
    {
        $name = $args->getArgument('name');
        $descriptorToUpdate = $this->discoveryManager->getRootTypeDescriptor($name);
        $bindingParams = $descriptorToUpdate->getType()->getParameters();
        $description = $descriptorToUpdate->getDescription();
        $paramDescriptions = $descriptorToUpdate->getParameterDescriptions();

        $this->parseParamDescriptions($args, $paramDescriptions);
        $this->parseParams($args, $bindingParams);
        $this->parseUnsetParams($args, $bindingParams, $paramDescriptions);

        if ($args->isOptionSet('description')) {
            $description = $args->getOption('description');
        }

        $updatedDescriptor = new BindingTypeDescriptor(
            new BindingType($name, $bindingParams),
            $description,
            $paramDescriptions
        );

        if ($this->typesEqual($descriptorToUpdate, $updatedDescriptor)) {
            throw new RuntimeException('Nothing to update.');
        }

        $this->discoveryManager->addRootTypeDescriptor($updatedDescriptor, DiscoveryManager::OVERRIDE);

        return 0;
    }

    /**
     * Handles the "puli type --delete" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleDelete(Args $args)
    {
        $typeName = $args->getArgument('name');

        if (!$this->discoveryManager->hasRootTypeDescriptor($typeName)) {
            throw new RuntimeException(sprintf(
                'The type "%s" does not exist in the package "%s".',
                $typeName,
                $this->packages->getRootPackageName()
            ));
        }

        $this->discoveryManager->removeRootTypeDescriptor($typeName);

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
     * @param IO                      $io          The I/O.
     * @param BindingTypeDescriptor[] $descriptors The type descriptors to print.
     * @param string                  $styleTag    The tag used to style the output
     * @param int                     $indentation The number of spaces to indent.
     */
    private function printTypeTable(IO $io, array $descriptors, $styleTag = null, $indentation = 0)
    {
        $table = new Table(PuliTableStyle::borderless());

        $table->setHeaderRow(array('Type', 'Description', 'Parameters'));

        $paramTag = $styleTag ?: 'c1';
        $typeTag = $styleTag ?: 'u';

        foreach ($descriptors as $descriptor) {
            $type = $descriptor->getType();
            $parameters = array();

            foreach ($type->getParameters() as $parameter) {
                $paramString = $parameter->isRequired()
                    ? $parameter->getName()
                    : $parameter->getName().'='.StringUtil::formatValue($parameter->getDefaultValue());

                $parameters[$parameter->getName()] = "<$paramTag>$paramString</$paramTag>";
            }

            $description = $descriptor->getDescription();

            if ($styleTag) {
                $description = "<$styleTag>$description</$styleTag>";
            }

            ksort($parameters);

            $table->addRow(array(
                "<$typeTag>".$descriptor->getTypeName()."</$typeTag>",
                $description,
                implode("\n", $parameters),
            ));
        }

        $table->render($io, $indentation);
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
                $io->writeLine('The following binding types are currently enabled:');
                $io->writeLine('');

                return;
            case BindingTypeState::DUPLICATE:
                $io->writeLine('The following types have duplicate definitions and are disabled:');
                $io->writeLine('');

                return;
        }
    }

    private function parseParamDescriptions(Args $args, array &$paramDescriptions)
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

    private function parseParams(Args $args, array &$bindingParams)
    {
        foreach ($args->getOption('param') as $parameter) {
            // Optional parameter with default value
            if (false !== ($pos = strpos($parameter, '='))) {
                $key = substr($parameter, 0, $pos);

                $bindingParams[$key] = new BindingParameter(
                    $key,
                    BindingParameter::OPTIONAL,
                    StringUtil::parseValue(substr($parameter, $pos + 1))
                );

                continue;
            }

            // Required parameter
            $bindingParams[$parameter] = new BindingParameter(
                $parameter,
                BindingParameter::REQUIRED,
                null
            );
        }
    }

    private function parseUnsetParams(Args $args, array &$bindingParams, array &$paramDescriptions)
    {
        foreach ($args->getOption('unset-param') as $parameterName) {
            unset($bindingParams[$parameterName]);
            unset($paramDescriptions[$parameterName]);
        }
    }

    private function typesEqual(BindingTypeDescriptor $descriptor1, BindingTypeDescriptor $descriptor2)
    {
        return ($descriptor1->getTypeName() === $descriptor2->getTypeName() &&
            $descriptor1->getDescription() === $descriptor2->getDescription() &&
            $descriptor1->getParameterDescriptions() === $descriptor2->getParameterDescriptions() &&
            $descriptor1->getType()->getParameters() === $descriptor2->getType()->getParameters());
    }
}
