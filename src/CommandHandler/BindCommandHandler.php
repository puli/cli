<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\CommandHandler;

use Puli\Cli\Console\CommandHandler\AbstractCommandHandler;
use Puli\Cli\Util\StringUtil;
use Puli\RepositoryManager\Api\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingState;
use Puli\RepositoryManager\Api\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindCommandHandler extends AbstractCommandHandler
{
    /**
     * @var DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var PackageCollection
     */
    private $packages;

    public function __construct(DiscoveryManager $discoveryManager, PackageCollection $packages)
    {
        $this->discoveryManager = $discoveryManager;
        $this->packages = $packages;
    }

    protected function execute(InputInterface $input)
    {
        if ($input->getOption('delete')) {
            return $this->removeBinding($input->getOption('delete'));
        }

        if ($input->getOption('enable')) {
            return $this->enableBinding(
                $input->getOption('enable'),
                $this->getPackageNames($input, $this->packages->getInstalledPackageNames())
            );
        }

        if ($input->getOption('disable')) {
            return $this->disableBinding(
                $input->getOption('disable'),
                $this->getPackageNames($input, $this->packages->getInstalledPackageNames())
            );
        }

        if ($input->getArgument('resource-query')) {
            return $this->addBinding(
                $input->getArgument('resource-query'),
                $input->getOption('language'),
                $input->getArgument('type-name'),
                $input->getOption('param')
            );
        }

        $packageNames = $this->getPackageNames($input, $this->packages->getPackageNames());

        $bindingStates = $this->getBindingStates($input);

        return $this->listBindings($packageNames, $bindingStates);
    }

    private function addBinding($query, $language, $typeName, array $parameters)
    {
        $bindingParams = array();

        foreach ($parameters as $parameter) {
            $pos = strpos($parameter, '=');

            if (false === $pos) {
                // invalid parameter
            }

            $key = substr($parameter, 0, $pos);
            $value = StringUtil::parseValue(substr($parameter, $pos + 1));

            $bindingParams[$key] = $value;
        }

        $this->discoveryManager->addBinding(new BindingDescriptor($query, $typeName, $bindingParams, $language));

        return 0;
    }

    private function removeBinding($uuidPrefix)
    {
        $bindings = $this->discoveryManager->findBindings($uuidPrefix);

        if (0 === count($bindings)) {
            return 0;
        }

        if (count($bindings) > 1) {
            // ambiguous
        }

        $bindingToRemove = reset($bindings);
        $this->discoveryManager->removeBinding($bindingToRemove->getUuid());

        return 0;
    }

    private function enableBinding($uuidPrefix, array $packageNames)
    {
        $bindings = $this->discoveryManager->findBindings($uuidPrefix);

        if (0 === count($bindings)) {
            return 0;
        }

        if (count($bindings) > 1) {
            // ambiguous
        }

        $bindingToEnable = reset($bindings);
        $this->discoveryManager->enableBinding($bindingToEnable->getUuid(), $packageNames);

        return 0;
    }

    private function disableBinding($uuidPrefix, array $packageNames)
    {
        $bindings = $this->discoveryManager->findBindings($uuidPrefix);

        if (0 === count($bindings)) {
            return 0;
        }

        if (count($bindings) > 1) {
            // ambiguous
        }

        $bindingToDisable = reset($bindings);
        $this->discoveryManager->disableBinding($bindingToDisable->getUuid(), $packageNames);

        return 0;
    }

    /**
     * @param array $packageNames
     * @param array $bindingStates
     *
     * @return int
     */
    private function listBindings(array $packageNames, array $bindingStates)
    {
        $printBindingState = count($bindingStates) > 1;
        $printPackageName = count($packageNames) > 1;
        $printHeaders = $printBindingState || $printPackageName;

        foreach ($bindingStates as $bindingState) {
            $bindingStatePrinted = !$printBindingState;

            foreach ($packageNames as $packageName) {
                $bindings = $this->discoveryManager->getBindings($packageName, $bindingState);

                if (!$bindings) {
                    continue;
                }

                if (!$bindingStatePrinted) {
                    $this->printBindingState($bindingState);
                    $bindingStatePrinted = true;
                }

                if ($printPackageName) {
                    $prefix = $printBindingState ? '    ' : '';
                    $this->output->writeln("<h>$prefix$packageName</h>");
                }

                $styleTag = BindingState::ENABLED === $bindingState ? null : 'fg=red';

                $this->printBindingTable($bindings, $styleTag, $printBindingState);

                if ($printHeaders) {
                    $this->output->writeln('');
                }
            }
        }

        return 0;
    }

    /**
     * @param InputInterface    $input
     * @param PackageCollection $packages
     * @param array             $default
     *
     * @return string[]
     */
    private function getPackageNames(InputInterface $input, $default = array())
    {
        // Display all packages if "all" is set
        if ($input->getOption('all')) {
            return $this->packages->getPackageNames();
        }

        $packageNames = array();

        if ($input->getOption('root')) {
            $packageNames[] = $this->packages->getRootPackage()->getName();
        }

        foreach ($input->getOption('package') as $packageName) {
            $packageNames[] = $packageName;
        }

        return $packageNames ?: $default;
    }

    private function getBindingStates(InputInterface $input)
    {
        $states = array();

        if ($input->getOption('enabled')) {
            $states[] = BindingState::ENABLED;
        }

        if ($input->getOption('disabled')) {
            $states[] = BindingState::DISABLED;
        }

        if ($input->getOption('duplicate')) {
            $states[] = BindingState::DUPLICATE;
        }

        if ($input->getOption('undecided')) {
            $states[] = BindingState::UNDECIDED;
        }

        if ($input->getOption('held-back')) {
            $states[] = BindingState::HELD_BACK;
        }

        if ($input->getOption('invalid')) {
            $states[] = BindingState::INVALID;
        }

        return $states ?: BindingState::all();
    }

    /**
     * @param OutputInterface     $output
     * @param BindingDescriptor[] $bindings
     */
    private function printBindingTable(array $bindings, $styleTag = null, $indent = false)
    {
        $table = new Table($this->output);
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('');

        $prefix = $indent ? '    ' : '';
        $paramTag = $styleTag ?: 'comment';
        $uuidTag = $styleTag ?: 'comment';
        $queryTag = $styleTag ?: 'em';
        $typeTag = $styleTag ?: 'tt';

        foreach ($bindings as $binding) {
            $parameters = array();

            foreach ($binding->getParameterValues() as $parameterName => $value) {
                $parameters[] = $parameterName.'='.StringUtil::formatValue($value);
            }

            $uuid = substr($binding->getUuid(), 0, 6);
            $paramString = $parameters ? " <$paramTag>(".implode(', ', $parameters).")</$paramTag>" : '';

            $table->addRow(array(
                "$prefix<$uuidTag>".$uuid."</$uuidTag> ".
                "<$queryTag>".$binding->getQuery()."</$queryTag>",
                " <$typeTag>".$binding->getTypeName()."</$typeTag>".$paramString
            ));
        }

        $table->render();
    }

    private function printBindingState($bindingState)
    {
        switch ($bindingState) {
            case BindingState::ENABLED:
                $this->output->writeln('Enabled bindings:');
                $this->output->writeln('');
                return;
            case BindingState::DISABLED:
                $this->output->writeln('Disabled bindings:');
                $this->output->writeln(' (use "puli bind --enable <uuid>" to enable)');
                $this->output->writeln('');
                return;
            case BindingState::UNDECIDED:
                $this->output->writeln('Bindings that are neither enabled nor disabled:');
                $this->output->writeln(' (use "puli bind --enable <uuid>" to enable)');
                $this->output->writeln('');
                return;
            case BindingState::DUPLICATE:
                $this->output->writeln('The following bindings are duplicates and ignored:');
                $this->output->writeln('');
                return;
            case BindingState::HELD_BACK:
                $this->output->writeln('The following bindings are held back:');
                $this->output->writeln(' (install or fix their type definitions to enable)');
                $this->output->writeln('');
                return;
            case BindingState::INVALID:
                $this->output->writeln('The following bindings have invalid parameters:');
                $this->output->writeln(' (remove the binding and add again with correct parameters)');
                $this->output->writeln('');
                return;
        }
    }
}
