<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Updater;

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Strategy\StrategyInterface;
use Humbug\SelfUpdate\Updater;
use Humbug\SelfUpdate\VersionParser;
use InvalidArgumentException;
use Puli\Cli\PuliApplicationConfig;

class PuliStrategy implements StrategyInterface
{
    const ANY = 'any';
    const STABLE = 'stable';
    const UNSTABLE = 'unstable';

    const MANIFEST = 'https://puli.io/download/versions.json';
    const REMOTE_PHAR = 'https://puli.io/download/%s/puli.phar';

    /**
     * @var array
     */
    private static $stabilities = array(
        self::STABLE,
        self::UNSTABLE,
        self::ANY,
    );

    /**
     * @var string
     */
    private $stability = self::ANY;

    /**
     * Download the remote Phar file.
     *
     * @param Updater $updater
     */
    public function download(Updater $updater)
    {
        /* Switch remote request errors to HttpRequestExceptions */
        set_error_handler(array($updater, 'throwHttpRequestException'));

        $remoteUrl = sprintf(
            self::REMOTE_PHAR,
            $this->getCurrentRemoteVersion($updater)
        );

        $result = humbug_get_contents($remoteUrl);
        restore_error_handler();

        if (false === $result) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s', $remoteUrl
            ));
        }

        file_put_contents($updater->getTempPharFile(), $result);
    }

    /**
     * Retrieve the current version available remotely.
     *
     * @param Updater $updater
     *
     * @return string
     */
    public function getCurrentRemoteVersion(Updater $updater)
    {
        /* Switch remote request errors to HttpRequestExceptions */
        set_error_handler(array($updater, 'throwHttpRequestException'));
        $versions = json_decode(humbug_get_contents(self::MANIFEST), true);
        restore_error_handler();

        if (false === $versions) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s', self::MANIFEST
            ));
        }

        $versionParser = new VersionParser($versions);

        if ($this->getStability() === self::STABLE) {
            return $versionParser->getMostRecentStable();
        }

        if ($this->getStability() === self::UNSTABLE) {
            return $versionParser->getMostRecentUnstable();
        }

        return $versionParser->getMostRecentAll();
    }

    /**
     * Retrieve the current version of the local phar file.
     *
     * @param Updater $updater
     *
     * @return string
     */
    public function getCurrentLocalVersion(Updater $updater)
    {
        return PuliApplicationConfig::VERSION;
    }

    /**
     * Set target stability.
     *
     * @param string $stability
     */
    public function setStability($stability)
    {
        if (!in_array($stability, self::$stabilities, true)) {
            throw new InvalidArgumentException(
                'Invalid stability value. Must be one of "stable", "unstable" or "any".'
            );
        }

        $this->stability = $stability;
    }

    /**
     * Get target stability.
     *
     * @return string
     */
    public function getStability()
    {
        return $this->stability;
    }
}
