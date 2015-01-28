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

use Puli\Cli\CommandHandler\BindCommandHandler;
use Puli\Cli\Console\Command\Command;
use Puli\Cli\Console\Input\InputOption;
use Puli\Cli\Util\PuliFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->addOption('duplicate', null, InputOption::VALUE_NONE, 'Show duplicate bindings')
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

    public function run(InputInterface $input, OutputInterface $output)
    {
        $puli = PuliFactory::createPuli($output);
        $discoveryManager = $puli->getDiscoveryManager();
        $packages = $puli->getPackageManager()->getPackages();

        $handler = new BindCommandHandler($discoveryManager, $packages);

        return $handler->initialize($this, $output)->handle($input);
    }
}
