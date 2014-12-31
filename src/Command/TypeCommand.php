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
use Puli\RepositoryManager\Discovery\BindingParameterDescriptor;
use Puli\RepositoryManager\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Discovery\DiscoveryManager;
use Puli\RepositoryManager\ManagerFactory;
use Puli\RepositoryManager\Package\PackageManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the binding type')
            ->addOption('root', null, InputOption::VALUE_NONE, 'Show types of the root package')
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Show types of a package', null, 'package')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Show types of all packages')
            ->addOption('define', null, InputOption::VALUE_NONE, 'Add a type')
            ->addOption('description', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A human-readable description')
            ->addOption('param', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A type parameter in the form <param> or <param>=<default>')
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete a type')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = ManagerFactory::createProjectEnvironment(getcwd());
        $packageManager = ManagerFactory::createPackageManager($environment);
        $discoveryManager = ManagerFactory::createDiscoveryManager($environment);
        $packageNames = $this->getPackageNames($input, $packageManager);

        if ($input->getOption('define')) {
            return $this->addBindingType(
                $input->getArgument('name'),
                $input->getOption('description'),
                $input->getOption('param'),
                $discoveryManager
            );
        }

        return $this->listBindingTypes($output, $discoveryManager, $packageNames);
    }

    private function addBindingType($typeName, array $descriptions, array $parameters, DiscoveryManager $discoveryManager)
    {
        $bindingParams = array();

        // The first description is for the type
        $description = $descriptions ? array_shift($descriptions) : null;

        foreach ($parameters as $parameter) {
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

        $discoveryManager->addBindingType(new BindingTypeDescriptor(
            $typeName,
            $description,
            $bindingParams
        ));

        return 0;
    }

    /**
     * @param OutputInterface  $output
     * @param DiscoveryManager $discoveryManager
     * @param array            $packageNames
     *
     * @return int
     */
    private function listBindingTypes(OutputInterface $output, DiscoveryManager $discoveryManager, array $packageNames = array())
    {
        if (1 === count($packageNames)) {
            $types = $discoveryManager->getBindingTypes(reset($packageNames));
            $this->printTypeTable($output, $types);

            return 0;
        }

        foreach ($packageNames as $packageName) {
            $types = $discoveryManager->getBindingTypes($packageName);

            if (!$types) {
                continue;
            }

            $output->writeln("<b>$packageName</b>");
            $this->printTypeTable($output, $types);
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
     * @param OutputInterface         $output
     * @param BindingTypeDescriptor[] $types
     */
    private function printTypeTable(OutputInterface $output, array $types)
    {
        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('');

        foreach ($types as $type) {
            $parameters = array();

            foreach ($type->getParameters() as $parameter) {
                $parameters[] = $parameter->isRequired()
                    ? $parameter->getName()
                    : $parameter->getName().'='.StringUtil::formatValue($parameter->getDefaultValue());
            }

            $paramString = $parameters
                ? ' <comment>('.implode(', ', $parameters).')</comment>'
                : '';

            $table->addRow(array(
                '<tt>'.$type->getName().'</tt>',
                ' '.$type->getDescription().$paramString
            ));
        }

        $table->render();
    }
}
