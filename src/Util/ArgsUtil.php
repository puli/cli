<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Util;

use Puli\Manager\Api\Module\ModuleList;
use Webmozart\Console\Api\Args\Args;

/**
 * Utilities for inspecting {@link Args} instances.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ArgsUtil
{
    /**
     * Returns the modules selected in the console arguments.
     *
     * @param Args       $args    The console arguments
     * @param ModuleList $modules The available modules
     *
     * @return string[] The module names
     */
    public static function getModuleNames(Args $args, ModuleList $modules)
    {
        // Display all modules if "all" is set
        if ($args->isOptionSet('all')) {
            return $modules->getModuleNames();
        }

        $moduleNames = array();

        if ($args->isOptionSet('root')) {
            $moduleNames[] = $modules->getRootModuleName();
        }

        foreach ($args->getOption('module') as $moduleName) {
            $moduleNames[] = $moduleName;
        }

        return $moduleNames ?: $modules->getModuleNames();
    }

    /**
     * Returns the non-root modules selected in the console arguments.
     *
     * @param Args       $args    The console arguments
     * @param ModuleList $modules The available modules
     *
     * @return string[] The module names
     */
    public static function getModuleNamesWithoutRoot(Args $args, ModuleList $modules)
    {
        // Display all modules if "all" is set
        if ($args->isOptionSet('all')) {
            return $modules->getInstalledModuleNames();
        }

        $moduleNames = array();

        foreach ($args->getOption('module') as $moduleName) {
            $moduleNames[] = $moduleName;
        }

        return $moduleNames ?: $modules->getInstalledModuleNames();
    }

    private function __construct()
    {
    }
}
