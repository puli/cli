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
     * Returns the packages selected in the console arguments.
     *
     * @param Args       $args     The console arguments
     * @param ModuleList $packages The available packages
     *
     * @return string[] The package names
     */
    public static function getPackageNames(Args $args, ModuleList $packages)
    {
        // Display all packages if "all" is set
        if ($args->isOptionSet('all')) {
            return $packages->getModuleNames();
        }

        $packageNames = array();

        if ($args->isOptionSet('root')) {
            $packageNames[] = $packages->getRootModuleName();
        }

        foreach ($args->getOption('package') as $packageName) {
            $packageNames[] = $packageName;
        }

        return $packageNames ?: $packages->getModuleNames();
    }

    /**
     * Returns the non-root packages selected in the console arguments.
     *
     * @param Args       $args     The console arguments
     * @param ModuleList $packages The available packages
     *
     * @return string[] The package names
     */
    public static function getPackageNamesWithoutRoot(Args $args, ModuleList $packages)
    {
        // Display all packages if "all" is set
        if ($args->isOptionSet('all')) {
            return $packages->getInstalledModuleNames();
        }

        $packageNames = array();

        foreach ($args->getOption('package') as $packageName) {
            $packageNames[] = $packageName;
        }

        return $packageNames ?: $packages->getInstalledModuleNames();
    }

    private function __construct()
    {
    }
}
