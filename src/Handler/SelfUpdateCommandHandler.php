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

use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest;
use Puli\Cli\PuliApplicationConfig;

/**
 * Handles the "self-update" command.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SelfUpdateCommandHandler
{
    /**
     * The URL for downloading the manifest.json file.
     */
    const MANIFEST_URL = 'http://puli.io/downloads/manifest.json';

    /**
     * Handles the "self-update" command.
     *
     * @return int The status code.
     */
    public function handle()
    {
        $manager = new Manager(Manifest::loadFile(self::MANIFEST_URL));
        $manager->update(PuliApplicationConfig::VERSION);

        return 0;
    }
}
