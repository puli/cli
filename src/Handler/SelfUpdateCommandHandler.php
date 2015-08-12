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
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SelfUpdateCommandHandler
{
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
        $updateStrategy = new GithubStrategy();
        $updateStrategy->setPackageName('puli/cli');
        $updateStrategy->setStability($this->getStability($args));
        $updateStrategy->setPharName('puli.phar');
        $updateStrategy->setCurrentLocalVersion(PuliApplicationConfig::VERSION);

        // false: disable signed releases, otherwise the updater will look for
        // a *.pubkey file for the PHAR
        $updater = new Updater(null, false);
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

    private function getStability(Args $args)
    {
        if ($args->isOptionSet('stable')) {
            return GithubStrategy::STABLE;
        }

        if ($args->isOptionSet('unstable')) {
            return GithubStrategy::UNSTABLE;
        }

        foreach (array('-dev', '-alpha', '-beta') as $stability) {
            if (false !== strpos(PuliApplicationConfig::VERSION, $stability)) {
                return GithubStrategy::UNSTABLE;
            }
        }

        return GithubStrategy::STABLE;
    }
}
