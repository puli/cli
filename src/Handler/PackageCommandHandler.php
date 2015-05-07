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

use Puli\Cli\Util\StringUtil;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageManager;
use Puli\Manager\Api\Package\PackageState;
use Puli\Manager\Api\Package\RootPackage;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Console\UI\Style\TableStyle;
use Webmozart\Expression\Expr;
use Webmozart\PathUtil\Path;

/**
 * Handles the "package" command.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageCommandHandler
{
    /**
     * @var array
     */
    private static $stateStrings = array(
        PackageState::ENABLED => 'enabled',
        PackageState::NOT_FOUND => 'not-found',
        PackageState::NOT_LOADABLE => 'not-loadable',
    );

    /**
     * @var PackageManager
     */
    private $packageManager;

    /**
     * Creates the handler.
     *
     * @param PackageManager $packageManager The package manager.
     */
    public function __construct(PackageManager $packageManager)
    {
        $this->packageManager = $packageManager;
    }

    /**
     * Handles the "package list" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handleList(Args $args, IO $io)
    {
        $packages = $this->getSelectedPackages($args);

        if ($args->isOptionSet('format')) {
            $this->printPackagesWithFormat($io, $packages, $args->getOption('format'));
        } else {
            $this->printPackagesByState($io, $packages, $this->getSelectedStates($args));
        }

        return 0;
    }

    /**
     * Handles the "package install" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleInstall(Args $args)
    {
        $packageName = $args->getArgument('name');
        $installPath = Path::makeAbsolute($args->getArgument('path'), getcwd());
        $installer = $args->getOption('installer');

        $this->packageManager->installPackage($installPath, $packageName, $installer);

        return 0;
    }

    /**
     * Handles the "package rename" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleRename(Args $args)
    {
        $packageName = $args->getArgument('name');
        $newName = $args->getArgument('new-name');

        $this->packageManager->renamePackage($packageName, $newName);

        return 0;
    }

    /**
     * Handles the "package remove" command.
     *
     * @param Args $args The console arguments.
     *
     * @return int The status code.
     */
    public function handleRemove(Args $args)
    {
        $packageName = $args->getArgument('name');

        if (!$this->packageManager->hasPackage($packageName)) {
            throw new RuntimeException(sprintf(
                'The package "%s" is not installed.',
                $packageName
            ));
        }

        $this->packageManager->removePackage($packageName);

        return 0;
    }

    /**
     * Handles the "package clean" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handleClean(Args $args, IO $io)
    {
        $expr = Expr::same(PackageState::NOT_FOUND, Package::STATE);

        foreach ($this->packageManager->findPackages($expr) as $package) {
            $io->writeLine('Removing '.$package->getName());
            $this->packageManager->removePackage($package->getName());
        }

        return 0;
    }

    /**
     * Returns the package states that should be displayed for the given
     * console arguments.
     *
     * @param Args $args The console arguments.
     *
     * @return int[] A list of {@link PackageState} constants.
     */
    private function getSelectedStates(Args $args)
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

    /**
     * Returns the packages that should be displayed for the given console
     * arguments.
     *
     * @param Args $args The console arguments.
     *
     * @return PackageCollection The packages.
     */
    private function getSelectedPackages(Args $args)
    {
        $states = $this->getSelectedStates($args);
        $expr = Expr::valid();

        if ($states != PackageState::all()) {
            $expr = $expr->andIn($states, Package::STATE);
        }

        if ($args->isOptionSet('installer')) {
            $expr = $expr->andSame($args->getOption('installer'), Package::INSTALLER);
        }

        return $this->packageManager->findPackages($expr);
    }

    /**
     * Prints packages with intermediate headers for the package states.
     *
     * @param IO                $io       The I/O.
     * @param PackageCollection $packages The packages to print.
     * @param int[]             $states   The states to print.
     */
    private function printPackagesByState(IO $io, PackageCollection $packages, array $states)
    {
        $printStates = count($states) > 1;

        foreach ($states as $state) {
            $filteredPackages = array_filter($packages->toArray(), function (Package $package) use ($state) {
                return $state === $package->getState();
            });

            if (0 === count($filteredPackages)) {
                continue;
            }

            if ($printStates) {
                $this->printPackageState($io, $state);
            }

            if (PackageState::NOT_LOADABLE === $state) {
                $this->printNotLoadablePackages($io, $filteredPackages, $printStates);
            } else {
                $styleTag = PackageState::ENABLED === $state ? null : 'bad';
                $this->printPackageTable($io, $filteredPackages, $styleTag, $printStates);
            }

            if ($printStates) {
                $io->writeLine('');
            }
        }
    }

    /**
     * Prints packages using the given format.
     *
     * @param IO                $io       The I/O.
     * @param PackageCollection $packages The packages to print.
     * @param string            $format   The format string.
     */
    private function printPackagesWithFormat(IO $io, PackageCollection $packages, $format)
    {
        foreach ($packages as $package) {
            $installInfo = $package->getInstallInfo() ;

            $io->writeLine(strtr($format, array(
                '%name%' => $package->getName(),
                '%installer%' => $installInfo ? $installInfo->getInstallerName() : '',
                '%install_path%' => $package->getInstallPath(),
                '%state%' => self::$stateStrings[$package->getState()],
            )));
        }
    }

    /**
     * Prints the heading for a given package state.
     *
     * @param IO  $io           The I/O.
     * @param int $packageState The {@link PackageState} constant.
     */
    private function printPackageState(IO $io, $packageState)
    {
        switch ($packageState) {
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

    /**
     * Prints a list of packages in a table.
     *
     * @param IO        $io       The I/O.
     * @param Package[] $packages The packages.
     * @param string    $styleTag The tag used to style the output. If `null`,
     *                            the default colors are used.
     * @param bool      $indent   Whether to indent the output.
     */
    private function printPackageTable(IO $io, array $packages, $styleTag = null, $indent = false)
    {
        $table = new Table(TableStyle::borderless());

        $rootTag = $styleTag ?: 'b';
        $installerTag = $styleTag ?: 'c1';
        $pathTag = $styleTag ?: 'c2';

        ksort($packages);

        foreach ($packages as $package) {
            $packageName = $package->getName();
            $installInfo = $package->getInstallInfo();
            $installPath = $installInfo ? $installInfo->getInstallPath() : '';
            $installer = $installInfo ? $installInfo->getInstallerName() : '';
            $row = array();

            if ($package instanceof RootPackage) {
                $row[] = "<$rootTag>$packageName</$rootTag>";
            } else {
                $row[] = $styleTag ? "<$styleTag>$packageName</$styleTag>" : $packageName;
            }

            $row[] = $installer ? "<$installerTag>$installer</$installerTag>" : '';
            $row[] = $installPath ? "<$pathTag>$installPath</$pathTag>" : '';

            $table->addRow($row);
        }

        $table->render($io, $indent ? 4 : 0);
    }

    /**
     * Prints not-loadable packages in a table.
     *
     * @param IO        $io       The I/O.
     * @param Package[] $packages The not-loadable packages.
     * @param bool      $indent   Whether to indent the output.
     */
    private function printNotLoadablePackages(IO $io, array $packages, $indent = false)
    {
        $rootDir = $this->packageManager->getEnvironment()->getRootDirectory();
        $table = new Table(TableStyle::borderless());

        ksort($packages);

        foreach ($packages as $package) {
            $packageName = $package->getName();
            $loadErrors = $package->getLoadErrors();
            $errorMessage = '';

            foreach ($loadErrors as $loadError) {
                $errorMessage .= StringUtil::getShortClassName(get_class($loadError)).': '.$loadError->getMessage()."\n";
            }

            $errorMessage = rtrim($errorMessage);

            if (!$errorMessage) {
                $errorMessage = 'Unknown error.';
            }

            // Remove root directory
            $errorMessage = str_replace($rootDir.'/', '', $errorMessage);

            $table->addRow(array("<bad>$packageName</bad>:", "<bad>$errorMessage</bad>"));
        }

        $table->render($io, $indent ? 4 : 0);
    }
}
