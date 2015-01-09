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
use Puli\RepositoryManager\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Discovery\BindingTypeState;
use Puli\RepositoryManager\Discovery\DiscoveryManager;
use Puli\RepositoryManager\ManagerFactory;
use Puli\RepositoryManager\Package\PackageManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;
use Webmozart\Console\Input\InputOption;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TypeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('type')
            ->setDescription('Display and change binding types')
            ->addOption('root', null, InputOption::VALUE_NONE, 'Show types of the root package')
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Show types of a package', null, 'package')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Show types of all packages')
            ->addOption('enabled', null, InputOption::VALUE_NONE, 'Show enabled types')
            ->addOption('duplicate', null, InputOption::VALUE_NONE, 'Show duplicate types')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output);
        $factory = new ManagerFactory();
        $environment = $factory->createProjectEnvironment(getcwd());
        $packageManager = $factory->createPackageManager($environment);
        $discoveryManager = $factory->createDiscoveryManager($environment, $packageManager, $logger);
        $packages = $packageManager->getPackages();
        $packageNames = $this->getPackageNames($input, $packageManager, $packages->getPackageNames());
        $states = $this->getBindingTypeStates($input);

        return $this->listBindingTypes($output, $discoveryManager, $packageNames, $states);
    }

    /**
     * @param OutputInterface  $output
     * @param DiscoveryManager $discoveryManager
     * @param array            $packageNames
     *
     * @return int
     */
    private function listBindingTypes(OutputInterface $output, DiscoveryManager $discoveryManager, array $packageNames, array $states)
    {
        $printStates = count($states) > 1;
        $printPackageName = count($packageNames) > 1;
        $printHeaders = $printStates || $printPackageName;
        $printAdvice = false;

        foreach ($states as $state) {
            $statePrinted = !$printStates;

            foreach ($packageNames as $packageName) {
                $bindingTypes = $discoveryManager->getBindingTypes($packageName, $state);

                if (!$bindingTypes) {
                    continue;
                }

                if (!$statePrinted) {
                    $this->printBindingTypeState($output, $state);
                    $statePrinted = true;
                }

                if ($printPackageName) {
                    $prefix = $printStates ? '    ' : '';
                    $output->writeln("<h>$prefix$packageName</h>");
                }

                $styleTag = BindingTypeState::ENABLED === $state ? null : 'fg=red';

                $this->printTypeTable($output, $bindingTypes, $styleTag, $printStates);

                if ($printHeaders) {
                    $output->writeln('');

                    // Only print the advice if at least one type was printed
                    $printAdvice = true;
                }
            }
        }

        if ($printAdvice) {
            $output->writeln('Use "puli bind <resource> <type>" to bind a resource to a type.');
        }

        return 0;
    }

    private function getBindingTypeStates(InputInterface $input)
    {
        $states = array();

        if ($input->getOption('enabled')) {
            $states[] = BindingTypeState::ENABLED;
        }

        if ($input->getOption('duplicate')) {
            $states[] = BindingTypeState::DUPLICATE;
        }

        return $states ?: BindingTypeState::all();
    }

    /**
     * @param InputInterface $input
     * @param PackageManager $packageManager
     *
     * @return string[]|null
     */
    private function getPackageNames(InputInterface $input, PackageManager $packageManager, $default = array())
    {
        // Display all packages if "all" is set
        if ($input->getOption('all')) {
            return $packageManager->getPackages()->getPackageNames();
        }

        $packageNames = array();

        if ($input->getOption('root')) {
            $packageNames[] = $packageManager->getRootPackage()->getName();
        }

        foreach ($input->getOption('package') as $packageName) {
            $packageNames[] = $packageName;
        }

        return $packageNames ?: $default;
    }

    /**
     * @param OutputInterface         $output
     * @param BindingTypeDescriptor[] $types
     */
    private function printTypeTable(OutputInterface $output, array $types, $styleTag = null, $indent = false)
    {
        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('');

        $prefix = $indent ? '    ' : '';
        $paramTag = $styleTag ?: 'comment';
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
                "$prefix<$typeTag>".$type->getName()."</$typeTag>",
                " ".ltrim($description.$paramString)
            ));
        }

        $table->render();
    }

    private function printBindingTypeState(OutputInterface $output, $bindingState)
    {
        switch ($bindingState) {
            case BindingTypeState::ENABLED:
                $output->writeln('Enabled binding types:');
                $output->writeln('');
                return;
            case BindingTypeState::DUPLICATE:
                $output->writeln('The following types have duplicate definitions and are disabled:');
                $output->writeln('');
                return;
        }
    }
}
