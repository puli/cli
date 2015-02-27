<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Handler;

use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Package\PackageManager;
use Puli\RepositoryManager\Api\Package\PackageState;
use Puli\RepositoryManager\Api\Package\RootPackage;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\Rendering\Canvas;
use Webmozart\Console\Rendering\Dimensions;
use Webmozart\Console\Rendering\Element\Table;
use Webmozart\Console\Rendering\Element\TableStyle;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageHandler
{
    /**
     * @var PackageManager
     */
    private $packageManager;

    public function __construct(PackageManager $packageManager)
    {
        $this->packageManager = $packageManager;
    }

    public function handleList(Args $args, IO $io)
    {
        $rootDir = $this->packageManager->getEnvironment()->getRootDirectory();
        $states = $this->getPackageStates($args);
        $installer = $args->getOption('installer');
        $printStates = count($states) > 1;

        foreach ($states as $state) {
            $packages = $installer
                ? $this->packageManager->getPackagesByInstaller($installer, $state)
                : $this->packageManager->getPackages($state);

            if (0 === count($packages)) {
                continue;
            }

            if ($printStates) {
                $this->printPackageState($io, $state);
            }

            if (PackageState::NOT_LOADABLE === $state) {
                $this->printNotLoadablePackages($io, $packages, $rootDir, $printStates);
            } else {
                $styleTag = PackageState::ENABLED === $state ? null : 'fg=red';

                $this->printPackageTable($io, $packages, $styleTag, $printStates, !$installer);
            }

            if ($printStates) {
                $io->writeLine('');
            }
        }

        return 0;
    }


    public function handleInstall(Args $args)
    {
        $packageName = $args->getArgument('name');
        $installPath = Path::makeAbsolute($args->getArgument('path'), getcwd());
        $installer = $args->getOption('installer');

        $this->packageManager->installPackage($installPath, $packageName, $installer);

        return 0;
    }

    public function handleRemove(Args $args)
    {
        $this->packageManager->removePackage($args->getArgument('name'));

        return 0;
    }

    public function handleClean(Args $args, IO $io)
    {
        foreach ($this->packageManager->getPackages(PackageState::NOT_FOUND) as $package) {
            $io->writeLine('Removing '.$package->getName());
            $this->packageManager->removePackage($package->getName());
        }

        return 0;
    }

    private function getPackageStates(Args $args)
    {
        $states = array();

        if ($args->isOptionSet('enabled')) {
            $states[] = PackageState::ENABLED;
        }

        if ($args->isOptionSet('not-found')) {
            $states[] = PackageState::NOT_FOUND;
        }

        if ($args->isOptionSet('not-loadable')) {
            $states[] = PackageState::NOT_LOADABLE;
        }

        return $states ?: PackageState::all();
    }

    private function printPackageState(IO $io, $bindingState)
    {
        switch ($bindingState) {
            case PackageState::ENABLED:
                $io->writeLine('Enabled packages:');
                $io->writeLine('');
                return;
            case PackageState::NOT_FOUND:
                $io->writeLine('The following packages could not be found:');
                $io->writeLine(' (use "puli package clean" to remove)');
                $io->writeLine('');
                return;
            case PackageState::NOT_LOADABLE:
                $io->writeLine('The following packages could not be loaded:');
                $io->writeLine('');
                return;
        }
    }

    private function printPackageTable(IO $io, PackageCollection $packages, $styleTag = null, $indent = false, $addInstaller = true)
    {
        $canvas = new Canvas($io);
        $table = new Table(TableStyle::borderless());

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
                $row[] = "<$rootTag>$packageName</$rootTag>";
            } elseif ($styleTag) {
                $row[] = "<$styleTag>$packageName</$styleTag>";
            }

            if ($addInstaller) {
                $installer = $installInfo ? $installInfo->getInstallerName() : 'root';
                $row[] = "<$installerTag>$installer</$installerTag>";
            }

            $row[] = "<$pathTag>$installPath</$pathTag>";

            $table->addRow($row);
        }

        $table->render($canvas, $indent ? 4 : 0);
    }

    private function printNotLoadablePackages(IO $io, PackageCollection $packages, $rootDir, $indent = false)
    {
        $prefix = $indent ? '    ' : '';
        $packages = $packages->toArray();
        $dimensions = Dimensions::forCurrentWindow();
        $screenWidth = $dimensions->getWidth();

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

            $io->writeLine("$prefix<fg=red>$packageName: $wrappedErrorMessage</fg=red>");
        }
    }

    private function getShortClassName($className)
    {
        $pos = strrpos($className, '\\');

        return false === $pos ? $className : substr($className, $pos + 1);
    }
}
