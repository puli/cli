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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\CompositeCommand;
use Webmozart\Console\Input\InputOption;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TypeDefineCommand extends CompositeCommand
{
    protected function configure()
    {
        $this
            ->setName('type define')
            ->setDescription('Define a binding type')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the binding type')
            ->addOption('description', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A human-readable description')
            ->addOption('param', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A type parameter in the form <param> or <param>=<default>')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output);
        $factory = new ManagerFactory();
        $environment = $factory->createProjectEnvironment(getcwd());
        $packageManager = $factory->createPackageManager($environment);
        $discoveryManager = $factory->createDiscoveryManager($environment, $packageManager, $logger);

        return $this->addBindingType(
            $input->getArgument('name'),
            $input->getOption('description'),
            $input->getOption('param'),
            $discoveryManager
        );
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
}
