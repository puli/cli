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

use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Package\PackageManager;
use Puli\RepositoryManager\Api\Package\PackageState;
use Puli\RepositoryManager\Api\Package\RootPackage;
use Puli\RepositoryManager\Puli;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Puli\Cli\Console\Command\Command;
use Puli\Cli\Console\Input\InputOption;

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
        $puli = new Puli(getcwd());
        $states = $this->getPackageStates($input);
        $installer = $input->getOption('installer');

        // Don't inject logger. We get all the information we want in the
        // output anyway.
        $manager = $puli->getPackageManager();

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
        $rootDir = $manager->getEnvironment()->getRootDirectory();
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

            if (PackageState::NOT_LOADABLE === $state) {
                $this->printNotLoadablePackages($output, $packages, $rootDir, $printStates);
            } else {
                $styleTag = PackageState::ENABLED === $state ? null : 'fg=red';

                $this->printPackageTable($output, $packages, $styleTag, $printStates, !$installer);
            }

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
                $output->writeln(' (use "puli package clean" to remove)');
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
                $installer = $installInfo ? $installInfo->getInstallerName() : 'root';
                $row[] = " <$installerTag>$installer</$installerTag>";
            }

            $row[] = " <$pathTag>$installPath</$pathTag>";

            $table->addRow($row);
        }

        $table->render();
    }

    private function printNotLoadablePackages(OutputInterface $output, PackageCollection $packages, $rootDir, $indent = false)
    {
        $prefix = $indent ? '    ' : '';
        $packages = $packages->toArray();
        $dimensions = $this->getApplication()->getTerminalDimensions();
        $screenWidth = $dimensions[0] ?: 80;

        // Maintain one space to the right
        $screenWidth--;

        ksort($packages);

        foreach ($packages as $package) {
            $packageName = $package->getName();
            $loadErrors = $package->getLoadErrors();
            $errorMessage = '';

            foreach ($loadErrors as $loadError) {
                $errorMessage .= $this->getShortClassName(get_class($loadError)).': '.$loadError->getMessage()."\n";
            }

            $errorMessage = rtrim($errorMessage);

            if (!$errorMessage) {
                $errorMessage = 'Unknown error.';
            }

            // Remove root directory
            $errorMessage = str_replace($rootDir.'/', '', $errorMessage);

            // TODO switch to WrappedTable once we have it
            $errorPrefixLength = strlen($prefix.$packageName.': ');
            $errorPrefix = str_repeat(' ', $errorPrefixLength);
            $errorWidth = $screenWidth - $errorPrefixLength;

            $wrappedErrorMessage = wordwrap($errorMessage, $errorWidth);
            $wrappedErrorMessage = str_replace("\n", "\n".$errorPrefix, $wrappedErrorMessage);

            $output->writeln("$prefix<fg=red>$packageName: $wrappedErrorMessage</fg=red>");
        }
    }

    private function getShortClassName($className)
    {
        $pos = strrpos($className, '\\');

        return false === $pos ? $className : substr($className, $pos + 1);
    }
}
