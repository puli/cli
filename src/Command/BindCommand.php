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

use Puli\Cli\Util\StringUtil;
use Puli\RepositoryManager\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Discovery\DiscoveryManager;
use Puli\RepositoryManager\ManagerFactory;
use Puli\RepositoryManager\Package\PackageManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;
use Webmozart\Console\Input\InputOption;

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
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete a binding')
            ->addOption('language', null, InputOption::VALUE_REQUIRED, 'The language of the resource query', 'glob')
            ->addOption('param', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A binding parameter in the form <param>=<value>')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output);
        $environment = ManagerFactory::createProjectEnvironment(getcwd());
        $packageManager = ManagerFactory::createPackageManager($environment);
        $discoveryManager = ManagerFactory::createDiscoveryManager($environment, $packageManager, $logger);
        $packageNames = $this->getPackageNames($input, $packageManager);

        if ($input->getOption('delete')) {
            return $this->removeBinding(
                $input->getArgument('resource-query'),
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

        return $this->listBindings($output, $discoveryManager, $packageNames);
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

        $discoveryManager->addBinding($query, $typeName, $bindingParams, $language);

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

    /**
     * @param OutputInterface  $output
     * @param DiscoveryManager $discoveryManager
     * @param array            $packageNames
     *
     * @return int
     */
    private function listBindings(OutputInterface $output, DiscoveryManager $discoveryManager, array $packageNames = array())
    {
        if (1 === count($packageNames)) {
            $types = $discoveryManager->getBindings(reset($packageNames));
            $this->printBindingTable($output, $types);

            return 0;
        }

        foreach ($packageNames as $packageName) {
            $types = $discoveryManager->getBindingTypes($packageName);

            if (!$types) {
                continue;
            }

            $output->writeln("<b>$packageName</b>");
            $this->printBindingTable($output, $types);
            $output->writeln('');
        }

        return 0;
    }

    /**
     * @param InputInterface $input
     * @param PackageManager $packageManager
     *
     * @return string[]|null
     */
    private function getPackageNames(InputInterface $input, PackageManager $packageManager)
    {
        // Display all packages if "all" is set
        if ($input->getOption('all')) {
            return $packageManager->getPackages()->getPackageNames();
        }

        $packageNames = array();

        // Display root if "root" option is given or if no option is set
        if ($input->getOption('root') || !$input->getOption('package')) {
            $packageNames[] = $packageManager->getRootPackage()->getName();
        }

        foreach ($input->getOption('package') as $packageName) {
            $packageNames[] = $packageName;
        }

        return $packageNames;
    }

    /**
     * @param OutputInterface     $output
     * @param BindingDescriptor[] $bindings
     */
    private function printBindingTable(OutputInterface $output, array $bindings)
    {
        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('');

        foreach ($bindings as $binding) {
            $parameters = array();

            foreach ($binding->getParameters() as $parameter => $value) {
                $parameters[] = $parameter.'='.StringUtil::formatValue($value);
            }

            $paramString = $parameters
                ? ' <comment>('.implode(', ', $parameters).')</comment>'
                : '';

            $uuid = substr($binding->getUuid(), 0, 6);

            $table->addRow(array(
                '<comment>'.$uuid.'</comment> '.
                '<em>'.$binding->getQuery().'</em>',
                ' <tt>'.$binding->getTypeName().'</tt>'.$paramString
            ));
        }

        $table->render();
    }
}
