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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output);
        $environment = ManagerFactory::createProjectEnvironment(getcwd());
        $packageManager = ManagerFactory::createPackageManager($environment);
        $discoveryManager = ManagerFactory::createDiscoveryManager($environment, $packageManager, $logger);
        $packages = $packageManager->getPackages();
        $packageNames = $this->getPackageNames($input, $packageManager, $packages->getPackageNames());

        return $this->listBindingTypes($output, $discoveryManager, $packageNames);
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
