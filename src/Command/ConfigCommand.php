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
use Puli\RepositoryManager\Api\Package\RootPackageFileManager;
use Puli\RepositoryManager\Puli;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Puli\Cli\Console\Command\Command;
use Puli\Cli\Console\Input\InputOption;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('config')
            ->setDescription('Display and modify configuration values')
            ->addArgument('key', InputArgument::OPTIONAL, 'The configuration key')
            ->addArgument('value', InputArgument::OPTIONAL, 'The value to set for the configuration key')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Include default values in the output')
            ->addOption('delete', 'd', InputOption::VALUE_REQUIRED, 'Delete a configuration key')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $puli = new Puli(getcwd());
        $manager = $puli->getRootPackageFileManager();
        $all = $input->getOption('all');
        $key = $input->getArgument('key');

        if ($input->getOption('delete')) {
            return $this->removeValue($input->getOption('delete'), $manager);
        }

        if ($input->getArgument('value')) {
            return $this->setValue($key, $input->getArgument('value'), $manager);
        }

        if (false !== strpos($key, '*')) {
            return $this->listValues($output, $manager->findConfigKeys($key, $all, $all));
        }

        if ($key) {
            return $this->displayValue($output, $key, $manager);
        }

        return $this->listValues($output, $manager->getConfigKeys($all, $all));
    }

    private function setValue($key, $value, RootPackageFileManager $manager)
    {
        $manager->setConfigKey($key, StringUtil::parseValue($value));

        return 0;
    }

    private function removeValue($key, RootPackageFileManager $manager)
    {
        $keys = false !== strpos($key, '*')
            ? array_keys($manager->findConfigKeys($key))
            : array($key);

        $manager->removeConfigKeys($keys);

        return 0;
    }

    private function displayValue(OutputInterface $output, $key, RootPackageFileManager $manager)
    {
        $output->writeln(StringUtil::formatValue($manager->getConfigKey($key, null, true), false));

        return 0;
    }

    private function listValues(OutputInterface $output, array $values)
    {
        $this->printTable($output, $values);

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param                 $values
     */
    private function printTable(OutputInterface $output, $values)
    {
        foreach ($values as $key => $value) {
            $output->writeln("<comment>$key</comment> = ".StringUtil::formatValue($value, false));
        }
    }
}
