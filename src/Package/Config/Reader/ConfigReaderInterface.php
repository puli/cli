<?php

/*
 * This file is part of the Puli CLI package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Package\Config\Reader;

use Puli\Cli\Package\Config\PackageConfig;
use Puli\Cli\Package\Config\RootPackageConfig;

/**
 * Reads package configuration from a data source.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ConfigReaderInterface
{
    /**
     * Reads package configuration from a data source.
     *
     * @param mixed $source The data source.
     *
     * @return PackageConfig The configuration read from the data source.
     *
     * @throws ConfigReaderException If the data source cannot be read or
     *                               contains invalid configuration.
     */
    public function readConfig($source);

    /**
     * Reads root package configuration from a data source.
     *
     * @param mixed $source The data source.
     *
     * @return RootPackageConfig The configuration read from the data source.
     *
     * @throws ConfigReaderException If the data source cannot be read or
     *                               contains invalid configuration.
     */
    public function readRootConfig($source);
}
