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

use Puli\Cli\Style\PuliTableStyle;
use Puli\Cli\Util\StringUtil;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\ModuleManager;
use Puli\Manager\Api\Module\ModuleState;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Expression\Expr;
use Webmozart\PathUtil\Path;

/**
 * Handles the "package" command.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageCommandHandler
{
    /**
     * @var array
     */
    private static $stateStrings = array(
        ModuleState::ENABLED => 'enabled',
        ModuleState::NOT_FOUND => 'not-found',
        ModuleState::NOT_LOADABLE => 'not-loadable',
    );

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * Creates the handler.
     *
     * @param ModuleManager $moduleManager The package manager
     */
    public function __construct(ModuleManager $moduleManager)
    {
        $this->moduleManager = $moduleManager;
    }

    /**
     * Handles the "package --list" command.
     *
     * @param Args $args The console arguments
     * @param IO   $io   The I/O
     *
     * @return int The status code
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
     * Handles the "package --install" command.
     *
     * @param Args $args The console arguments
     *
     * @return int The status code
     */
    public function handleInstall(Args $args)
    {
        $packageName = $args->getArgument('name');
        $installPath = Path::makeAbsolute($args->getArgument('path'), getcwd());
        $installer = $args->getOption('installer');
        $env = $args->isOptionSet('dev') ? Environment::DEV : Environment::PROD;

        $this->moduleManager->installModule($installPath, $packageName, $installer, $env);

        return 0;
    }

    /**
     * Handles the "package --rename" command.
     *
     * @param Args $args The console arguments
     *
     * @return int The status code
     */
    public function handleRename(Args $args)
    {
        $packageName = $args->getArgument('name');
        $newName = $args->getArgument('new-name');

        $this->moduleManager->renameModule($packageName, $newName);

        return 0;
    }

    /**
     * Handles the "package --delete" command.
     *
     * @param Args $args The console arguments
     *
     * @return int The status code
     */
    public function handleDelete(Args $args)
    {
        $packageName = $args->getArgument('name');

        if (!$this->moduleManager->hasModule($packageName)) {
            throw new RuntimeException(sprintf(
                'The package "%s" is not installed.',
                $packageName
            ));
        }

        $this->moduleManager->removeModule($packageName);

        return 0;
    }

    /**
     * Handles the "package --clean" command.
     *
     * @param Args $args The console arguments
     * @param IO   $io   The I/O
     *
     * @return int The status code
     */
    public function handleClean(Args $args, IO $io)
    {
        $expr = Expr::method('getState', Expr::same(ModuleState::NOT_FOUND));

        foreach ($this->moduleManager->findModules($expr) as $module) {
            $io->writeLine('Removing '.$module->getName());
            $this->moduleManager->removeModule($module->getName());
        }

        return 0;
    }

    /**
     * Returns the package states that should be displayed for the given
     * console arguments.
     *
     * @param Args $args The console arguments
     *
     * @return int[] A list of {@link ModuleState} constants
     */
    private function getSelectedStates(Args $args)
    {
        $states = array();

        if ($args->isOptionSet('enabled')) {
            $states[] = ModuleState::ENABLED;
        }

        if ($args->isOptionSet('not-found')) {
            $states[] = ModuleState::NOT_FOUND;
        }

        if ($args->isOptionSet('not-loadable')) {
            $states[] = ModuleState::NOT_LOADABLE;
        }

        return $states ?: ModuleState::all();
    }

    /**
     * Returns the packages that should be displayed for the given console
     * arguments.
     *
     * @param Args $args The console arguments
     *
     * @return ModuleList The packages
     */
    private function getSelectedPackages(Args $args)
    {
        $states = $this->getSelectedStates($args);
        $expr = Expr::true();
        $envs = array();

        if ($states !== ModuleState::all()) {
            $expr = $expr->andMethod('getState', Expr::in($states));
        }

        if ($args->isOptionSet('installer')) {
            $expr = $expr->andMethod('getInstallInfo', Expr::method('getInstallerName', Expr::same($args->getOption('installer'))));
        }

        if ($args->isOptionSet('prod')) {
            $envs[] = Environment::PROD;
        }

        if ($args->isOptionSet('dev')) {
            $envs[] = Environment::DEV;
        }

        if (count($envs) > 0) {
            $expr = $expr->andMethod('getInstallInfo', Expr::method('getEnvironment', Expr::in($envs)));
        }

        return $this->moduleManager->findModules($expr);
    }

    /**
     * Prints packages with intermediate headers for the package states.
     *
     * @param IO         $io      The I/O
     * @param ModuleList $modules The packages to print
     * @param int[]      $states  The states to print
     */
    private function printPackagesByState(IO $io, ModuleList $modules, array $states)
    {
        $printStates = count($states) > 1;

        foreach ($states as $state) {
            $filteredPackages = array_filter($modules->toArray(), function (Module $module) use ($state) {
                return $state === $module->getState();
            });

            if (0 === count($filteredPackages)) {
                continue;
            }

            if ($printStates) {
                $this->printModuleState($io, $state);
            }

            if (ModuleState::NOT_LOADABLE === $state) {
                $this->printNotLoadablePackages($io, $filteredPackages, $printStates);
            } else {
                $styleTag = ModuleState::ENABLED === $state ? null : 'bad';
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
     * @param IO         $io      The I/O
     * @param ModuleList $modules The packages to print
     * @param string     $format  The format string
     */
    private function printPackagesWithFormat(IO $io, ModuleList $modules, $format)
    {
        /** @var Module $module */
        foreach ($modules as $module) {
            $installInfo = $module->getInstallInfo();

            $io->writeLine(strtr($format, array(
                '%name%' => $module->getName(),
                '%installer%' => $installInfo ? $installInfo->getInstallerName() : '',
                '%install_path%' => $module->getInstallPath(),
                '%state%' => self::$stateStrings[$module->getState()],
                '%env%' => $installInfo ? $installInfo->getEnvironment() : Environment::PROD,
            )));
        }
    }

    /**
     * Prints the heading for a given package state.
     *
     * @param IO  $io          The I/O
     * @param int $ModuleState The {@link ModuleState} constant
     */
    private function printModuleState(IO $io, $ModuleState)
    {
        switch ($ModuleState) {
            case ModuleState::ENABLED:
                $io->writeLine('The following packages are currently enabled:');
                $io->writeLine('');

                return;
            case ModuleState::NOT_FOUND:
                $io->writeLine('The following packages could not be found:');
                $io->writeLine(' (use "puli package --clean" to remove)');
                $io->writeLine('');

                return;
            case ModuleState::NOT_LOADABLE:
                $io->writeLine('The following packages could not be loaded:');
                $io->writeLine('');

                return;
        }
    }

    /**
     * Prints a list of packages in a table.
     *
     * @param IO          $io       The I/O
     * @param Module[]    $modules  The packages
     * @param string|null $styleTag The tag used to style the output. If `null`,
     *                              the default colors are used
     * @param bool        $indent   Whether to indent the output
     */
    private function printPackageTable(IO $io, array $modules, $styleTag = null, $indent = false)
    {
        $table = new Table(PuliTableStyle::borderless());
        $table->setHeaderRow(array('Package Name', 'Installer', 'Env', 'Install Path'));

        $installerTag = $styleTag ?: 'c1';
        $envTag = $styleTag ?: 'c1';
        $pathTag = $styleTag ?: 'c2';

        ksort($modules);

        foreach ($modules as $package) {
            $packageName = $package->getName();
            $installInfo = $package->getInstallInfo();
            $installPath = $installInfo ? $installInfo->getInstallPath() : '.';
            $installer = $installInfo ? $installInfo->getInstallerName() : '';
            $env = $installInfo ? $installInfo->getEnvironment() : Environment::PROD;

            $table->addRow(array(
                $styleTag ? sprintf('<%s>%s</%s>', $styleTag, $packageName, $styleTag) : $packageName,
                $installer ? sprintf('<%s>%s</%s>', $installerTag, $installer, $installerTag) : '',
                sprintf('<%s>%s</%s>', $envTag, $env, $envTag),
                sprintf('<%s>%s</%s>', $pathTag, $installPath, $pathTag),
            ));
        }

        $table->render($io, $indent ? 4 : 0);
    }

    /**
     * Prints not-loadable packages in a table.
     *
     * @param IO       $io      The I/O
     * @param Module[] $modules The not-loadable packages
     * @param bool     $indent  Whether to indent the output
     */
    private function printNotLoadablePackages(IO $io, array $modules, $indent = false)
    {
        $rootDir = $this->moduleManager->getContext()->getRootDirectory();
        $table = new Table(PuliTableStyle::borderless());
        $table->setHeaderRow(array('Package Name', 'Error'));

        ksort($modules);

        foreach ($modules as $package) {
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

            $table->addRow(array(
                sprintf('<bad>%s</bad>', $packageName),
                sprintf('<bad>%s</bad>', $errorMessage),
            ));
        }

        $table->render($io, $indent ? 4 : 0);
    }
}
