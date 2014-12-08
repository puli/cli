<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli;

/**
 * Contains metadata of the Puli CLI library.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliCli
{
    const VERSION = '@package_version@';

    const RELEASE_DATE = '@release_date@';

    private function __construct()
    {
    }
}
