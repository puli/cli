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
use Puli\RepositoryManager\ManagerFactory;
use Puli\RepositoryManager\Package\PackageFile\PackageFileManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;

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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = ManagerFactory::createProjectEnvironment(getcwd());
        $manager = ManagerFactory::createPackageFileManager($environment);

        if ($input->getArgument('value')) {
            return $this->setValue($input->getArgument('key'), $input->getArgument('value'), $manager);
        }

        if ($input->getArgument('key')) {
            return $this->displayValue($output, $input->getArgument('key'), $manager);
        }

        return $this->listValues($output, $manager);
    }

    private function setValue($key, $value, PackageFileManager $manager)
    {
        $manager->setConfigKey($key, StringUtil::parseValue($value));

        return 0;
    }

    private function displayValue(OutputInterface $output, $key, PackageFileManager $manager)
    {
        $output->writeln(StringUtil::formatValue($manager->getConfigKey($key)));

        return 0;
    }

    private function listValues(OutputInterface $output, PackageFileManager $manager)
    {
        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('');

        $previousHeading = null;

        foreach ($manager->getConfigKeys() as $key => $value) {
            $heading = $this->getHeading($key);

            if ($heading !== $previousHeading) {
                if ($previousHeading) {
                    $table->addRow(array(''));
                }

                $table->addRow(array("<h>$heading</h>"));
                $previousHeading = $heading;
            }

            $table->addRow(array(
                "<comment>$key</comment>",
                ' '.StringUtil::formatValue($value)
            ));
        }

        $table->render();

        $output->writeln('');

        return 0;
    }

    private function getHeading($key)
    {
        if (false !== ($pos = strpos($key, '.'))) {
            return '['.substr($key, 0, $pos).']';
        }

        return '[general]';
    }
}
