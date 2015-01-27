<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Command;

use Psr\Log\LogLevel;
use Puli\Cli\Util\StringUtil;
use Puli\RepositoryManager\Api\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingState;
use Puli\RepositoryManager\Api\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Puli;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Puli\Cli\Console\Command\Command;
use Puli\Cli\Console\Input\InputOption;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('bind')
            ->setDescription('Bind resources to binding types')
            ->addArgument('resource-query', InputArgument::OPTIONAL, 'A query for resources')
            ->addArgument('type-name', InputArgument::OPTIONAL, 'The name of the binding type')
            ->addOption('root', null, InputOption::VALUE_NONE, 'Show bindings of the root package')
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Show bindings of a package', null, 'package')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Show bindings of all packages')
            ->addOption('enabled', null, InputOption::VALUE_NONE, 'Show enabled bindings')
            ->addOption('disabled', null, InputOption::VALUE_NONE, 'Show disabled bindings')
            ->addOption('undecided', null, InputOption::VALUE_NONE, 'Show bindings that are neither enabled nor disabled')
            ->addOption('held-back', null, InputOption::VALUE_NONE, 'Show bindings whose type is not loaded')
            ->addOption('ignored', null, InputOption::VALUE_NONE, 'Show bindings whose type is disabled')
            ->addOption('invalid', null, InputOption::VALUE_NONE, 'Show bindings with invalid parameters')
            ->addOption('delete', 'd', InputOption::VALUE_REQUIRED, 'Delete a binding')
            ->addOption('enable', null, InputOption::VALUE_REQUIRED, 'Enable a binding')
            ->addOption('disable', null, InputOption::VALUE_REQUIRED, 'Disable a binding')
            ->addOption('language', null, InputOption::VALUE_REQUIRED, 'The language of the resource query', 'glob')
            ->addOption('param', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A binding parameter in the form <param>=<value>')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output, array(), array(
            LogLevel::WARNING => 'warn',
        ));

        $puli = new Puli(getcwd());
        $puli->setLogger($logger);
        $discoveryManager = $puli->getDiscoveryManager();
        $packages = $puli->getPackageManager()->getPackages();

        if ($input->getOption('delete')) {
            return $this->removeBinding(
                $input->getOption('delete'),
                $discoveryManager
            );
        }

        if ($input->getOption('enable')) {
            return $this->enableBinding(
                $input->getOption('enable'),
                $this->getPackageNames($input, $packages, $packages->getInstalledPackageNames()),
                $discoveryManager
            );
        }

        if ($input->getOption('disable')) {
            return $this->disableBinding(
                $input->getOption('disable'),
                $this->getPackageNames($input, $packages, $packages->getInstalledPackageNames()),
                $discoveryManager
            );
        }

        if ($input->getArgument('resource-query')) {
            return $this->addBinding(
                $input->getArgument('resource-query'),
                $input->getOption('language'),
                $input->getArgument('type-name'),
                $input->getOption('param'),
                $discoveryManager
            );
        }

        $packageNames = $this->getPackageNames($input, $packages, $packages->getPackageNames());

        $bindingStates = $this->getBindingStates($input, $discoveryManager);

        return $this->listBindings($output, $discoveryManager, $packageNames, $bindingStates);
    }

    private function addBinding($query, $language, $typeName, array $parameters, DiscoveryManager $discoveryManager)
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

        $discoveryManager->addBinding(new BindingDescriptor($query, $typeName, $bindingParams, $language));

        return 0;
    }

    private function removeBinding($uuidPrefix, DiscoveryManager $discoveryManager)
    {
        $bindings = $discoveryManager->findBindings($uuidPrefix);

        if (0 === count($bindings)) {
            return 0;
        }

        if (count($bindings) > 1) {
            // ambiguous
        }

        $bindingToRemove = reset($bindings);
        $discoveryManager->removeBinding($bindingToRemove->getUuid());

        return 0;
    }

    private function enableBinding($uuidPrefix, array $packageNames, DiscoveryManager $discoveryManager)
    {
        $bindings = $discoveryManager->findBindings($uuidPrefix);

        if (0 === count($bindings)) {
            return 0;
        }

        if (count($bindings) > 1) {
            // ambiguous
        }

        $bindingToEnable = reset($bindings);
        $discoveryManager->enableBinding($bindingToEnable->getUuid(), $packageNames);

        return 0;
    }

    private function disableBinding($uuidPrefix, array $packageNames, DiscoveryManager $discoveryManager)
    {
        $bindings = $discoveryManager->findBindings($uuidPrefix);

        if (0 === count($bindings)) {
            return 0;
        }

        if (count($bindings) > 1) {
            // ambiguous
        }

        $bindingToDisable = reset($bindings);
        $discoveryManager->disableBinding($bindingToDisable->getUuid(), $packageNames);

        return 0;
    }

    /**
     * @param OutputInterface  $output
     * @param DiscoveryManager $discoveryManager
     * @param array            $packageNames
     *
     * @return int
     */
    private function listBindings(OutputInterface $output, DiscoveryManager $discoveryManager, array $packageNames, array $bindingStates)
    {
        $printBindingState = count($bindingStates) > 1;
        $printPackageName = count($packageNames) > 1;
        $printHeaders = $printBindingState || $printPackageName;

        foreach ($bindingStates as $bindingState) {
            $bindingStatePrinted = !$printBindingState;

            foreach ($packageNames as $packageName) {
                $bindings = $discoveryManager->getBindings($packageName, $bindingState);

                if (!$bindings) {
                    continue;
                }

                if (!$bindingStatePrinted) {
                    $this->printBindingState($output, $bindingState);
                    $bindingStatePrinted = true;
                }

                if ($printPackageName) {
                    $prefix = $printBindingState ? '    ' : '';
                    $output->writeln("<h>$prefix$packageName</h>");
                }

                $styleTag = BindingState::ENABLED === $bindingState ? null : 'fg=red';

                $this->printBindingTable($output, $bindings, $styleTag, $printBindingState);

                if ($printHeaders) {
                    $output->writeln('');
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
    private function getPackageNames(InputInterface $input, PackageCollection $packages, $default = array())
    {
        // Display all packages if "all" is set
        if ($input->getOption('all')) {
            return $packages->getPackageNames();
        }

        $packageNames = array();

        if ($input->getOption('root')) {
            $packageNames[] = $packages->getRootPackage()->getName();
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
    private function printBindingTable(OutputInterface $output, array $bindings, $styleTag = null, $indent = false)
    {
        $table = new Table($output);
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

    private function printBindingState(OutputInterface $output, $bindingState)
    {
        switch ($bindingState) {
            case BindingState::ENABLED:
                $output->writeln('Enabled bindings:');
                $output->writeln('');
                return;
            case BindingState::DISABLED:
                $output->writeln('Disabled bindings:');
                $output->writeln(' (use "puli bind --enable <uuid>" to enable)');
                $output->writeln('');
                return;
            case BindingState::UNDECIDED:
                $output->writeln('Bindings that are neither enabled nor disabled:');
                $output->writeln(' (use "puli bind --enable <uuid>" to enable)');
                $output->writeln('');
                return;
            case BindingState::HELD_BACK:
                $output->writeln('The following bindings are held back:');
                $output->writeln(' (install or fix their type definitions to enable)');
                $output->writeln('');
                return;
            case BindingState::INVALID:
                $output->writeln('The following bindings have invalid parameters:');
                $output->writeln(' (remove the binding and add again with correct parameters)');
                $output->writeln('');
                return;
        }
    }
}
