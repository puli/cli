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

use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use Puli\Cli\PuliApplicationConfig;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

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
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handle(Args $args, IO $io)
    {
        $stable = true;

        foreach (array('-dev', '-alpha', '-beta') as $stability) {
            if (false !== strpos(PuliApplicationConfig::VERSION, $stability)) {
                $stable = false;
                break;
            }
        }

        $updateStrategy = new GithubStrategy();
        $updateStrategy->setPackageName('puli/cli');
        $updateStrategy->setStability($stable ? GithubStrategy::STABLE : GithubStrategy::UNSTABLE);
        $updateStrategy->setPharName('puli.phar');
        $updateStrategy->setCurrentLocalVersion(PuliApplicationConfig::VERSION);

        $updater = new Updater();
        $updater->setStrategyObject($updateStrategy);

        if ($updater->update()) {
            $io->writeLine(sprintf(
                'Updated from version %s to version %s.',
                $updater->getOldVersion(),
                $updater->getNewVersion()
            ));
        } else {
            $io->writeLine(sprintf(
                'Version %s is the latest version. No update required.',
                $updater->getOldVersion()
            ));
        }

        return 0;
    }
}
