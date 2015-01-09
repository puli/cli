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

use Puli\RepositoryManager\ManagerFactory;
use Puli\RepositoryManager\Package\PackageCollection;
use Puli\RepositoryManager\Package\PackageManager;
use Puli\RepositoryManager\Package\PackageState;
use Puli\RepositoryManager\Package\RootPackage;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;
use Webmozart\Console\Input\InputOption;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('package')
            ->setDescription('Display the installed packages')
            ->addOption('installer', null, InputOption::VALUE_REQUIRED, 'Show packages installed by a specific installer')
            ->addOption('enabled', null, InputOption::VALUE_NONE, 'Show enabled packages')
            ->addOption('not-found', null, InputOption::VALUE_NONE, 'Show packages that could not be found')
            ->addOption('not-loadable', null, InputOption::VALUE_NONE, 'Show packages that could not be loaded')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $factory = new ManagerFactory();
        $environment = $factory->createProjectEnvironment(getcwd());
        $states = $this->getPackageStates($input);
        $installer = $input->getOption('installer');

        // Don't inject logger. We get all the information we want in the
        // output anyway.
        $manager = $factory->createPackageManager($environment);

        return $this->listPackages($output, $manager, $states, $installer);
    }

    /**
     * @param OutputInterface $output
     * @param PackageManager  $manager
     *
     * @return int
     */
    private function listPackages(OutputInterface $output, PackageManager $manager, array $states, $installer)
    {
        $printStates = count($states) > 1;

        foreach ($states as $state) {
            $packages = $installer
                ? $manager->getPackagesByInstaller($installer, $state)
                : $manager->getPackages($state);

            if (0 === count($packages)) {
                continue;
            }

            if ($printStates) {
                $this->printPackageState($output, $state);
            }

            $styleTag = PackageState::ENABLED === $state ? null : 'fg=red';

            $this->printPackageTable($output, $packages, $styleTag, $printStates, !$installer);

            if ($printStates) {
                $output->writeln('');
            }
        }

        return 0;
    }

    private function getPackageStates(InputInterface $input)
    {
        $states = array();

        if ($input->getOption('enabled')) {
            $states[] = PackageState::ENABLED;
        }

        if ($input->getOption('not-found')) {
            $states[] = PackageState::NOT_FOUND;
        }

        if ($input->getOption('not-loadable')) {
            $states[] = PackageState::NOT_LOADABLE;
        }

        return $states ?: PackageState::all();
    }

    private function printPackageState(OutputInterface $output, $bindingState)
    {
        switch ($bindingState) {
            case PackageState::ENABLED:
                $output->writeln('Enabled packages:');
                $output->writeln('');
                return;
            case PackageState::NOT_FOUND:
                $output->writeln('The following packages could not be found:');
                $output->writeln('');
                return;
            case PackageState::NOT_LOADABLE:
                $output->writeln('The following packages could not be loaded:');
                $output->writeln('');
                return;
        }
    }

    private function printPackageTable(OutputInterface $output, PackageCollection $packages, $styleTag = null, $indent = false, $addInstaller = true)
    {
        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('');

        $prefix = $indent ? '    ' : '';
        $rootTag = $styleTag ?: 'b';
        $installerTag = $styleTag ?: 'em';
        $pathTag = $styleTag ?: 'comment';
        $packages = $packages->toArray();

        ksort($packages);

        foreach ($packages as $package) {
            $packageName = $package->getName();
            $installInfo = $package->getInstallInfo();
            $installPath = $installInfo ? $installInfo->getInstallPath() : '';
            $row = array();

            if ($package instanceof RootPackage) {
                $packageName = "<$rootTag>$packageName</$rootTag>";
            } elseif ($styleTag) {
                $packageName = "<$styleTag>$packageName</$styleTag>";
            }

            $row[] = $prefix.$packageName;

            if ($addInstaller) {
                $installer = $installInfo ? $installInfo->getInstaller() : 'root';
                $row[] = " <$installerTag>$installer</$installerTag>";
            }

            $row[] = " <$pathTag>$installPath</$pathTag>";

            $table->addRow($row);
        }

        $table->render();
    }
}
