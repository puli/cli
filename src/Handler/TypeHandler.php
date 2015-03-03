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

use Puli\Cli\Util\StringUtil;
use Puli\RepositoryManager\Api\Discovery\BindingParameterDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingTypeState;
use Puli\RepositoryManager\Api\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Api\Package\PackageManager;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\Rendering\Canvas;
use Webmozart\Console\Rendering\Element\Table;
use Webmozart\Console\Rendering\Element\TableStyle;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TypeHandler
{
    /**
     * @var DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var PackageManager
     */
    private $packageManager;

    public function __construct(DiscoveryManager $discoveryManager, PackageManager $packageManager)
    {
        $this->discoveryManager = $discoveryManager;
        $this->packageManager = $packageManager;
    }

    public function handleList(Args $args, IO $io)
    {
        $packages = $this->packageManager->getPackages();
        $packageNames = $this->getPackageNames($args, $packages->getPackageNames());
        $states = $this->getBindingTypeStates($args);

        $printStates = count($states) > 1;
        $printPackageName = count($packageNames) > 1;
        $printHeaders = $printStates || $printPackageName;
        $printAdvice = false;

        foreach ($states as $state) {
            $statePrinted = !$printStates;

            foreach ($packageNames as $packageName) {
                $bindingTypes = $this->discoveryManager->getBindingTypes($packageName, $state);

                if (!$bindingTypes) {
                    continue;
                }

                if (!$statePrinted) {
                    $this->printBindingTypeState($io, $state);
                    $statePrinted = true;
                }

                if ($printPackageName) {
                    $prefix = $printStates ? '    ' : '';
                    $io->writeLine("<h>$prefix$packageName</h>");
                }

                $styleTag = BindingTypeState::ENABLED === $state ? null : 'fg=red';

                $this->printTypeTable($io, $bindingTypes, $styleTag, $printStates);

                if ($printHeaders) {
                    $io->writeLine('');

                    // Only print the advice if at least one type was printed
                    $printAdvice = true;
                }
            }
        }

        if ($printAdvice) {
            $io->writeLine('Use "puli bind <resource> <type>" to bind a resource to a type.');
        }

        return 0;
    }

    public function handleDefine(Args $args)
    {
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
                    false,
                    StringUtil::parseValue(substr($parameter, $pos + 1)),
                    $paramDescription
                );

                continue;
            }

            // Required parameter
            $bindingParams[] = new BindingParameterDescriptor(
                $parameter,
                true,
                null,
                $paramDescription
            );
        }

        $this->discoveryManager->addBindingType(new BindingTypeDescriptor(
            $args->getArgument('name'),
            $description,
            $bindingParams
        ));

        return 0;
    }

    public function handleRemove(Args $args)
    {
        $this->discoveryManager->removeBindingType($args->getArgument('name'));

        return 0;
    }

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

    private function getPackageNames(Args $args, $default = array())
    {
        // Display all packages if "all" is set
        if ($args->isOptionSet('all')) {
            return $this->packageManager->getPackages()->getPackageNames();
        }

        $packageNames = array();

        if ($args->isOptionSet('root')) {
            $packageNames[] = $this->packageManager->getRootPackage()->getName();
        }

        foreach ($args->getOption('package') as $packageName) {
            $packageNames[] = $packageName;
        }

        return $packageNames ?: $default;
    }

    /**
     * @param IO                      $io
     * @param BindingTypeDescriptor[] $types
     * @param null                    $styleTag
     * @param bool                    $indent
     */
    private function printTypeTable(IO $io, array $types, $styleTag = null, $indent = false)
    {
        $canvas = new Canvas($io);
        $table = new Table(TableStyle::borderless());

        $paramTag = $styleTag ?: 'em';
        $typeTag = $styleTag ?: 'tt';

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

        $table->render($canvas, $indent ? 4 : 0);
    }

    private function printBindingTypeState(IO $io, $bindingState)
    {
        switch ($bindingState) {
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
