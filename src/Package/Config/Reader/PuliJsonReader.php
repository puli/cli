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

use Puli\Cli\Json\JsonReader;
use Puli\Cli\Json\InvalidJsonException;
use Puli\Cli\Package\Config\PackageConfig;
use Puli\Cli\Package\Config\ResourceDefinition;
use Puli\Cli\Package\Config\RootPackageConfig;
use Puli\Cli\Package\Config\TagDefinition;

/**
 * Reads package configuration from a JSON file.
 *
 * The data in the JSON file is validated against the schema
 * `res/schema/config-schema.json`.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliJsonReader implements ConfigReaderInterface
{
    /**
     * Reads package configuration from a JSON file.
     *
     * The data in the JSON file is validated against the schema
     * `res/schema/config-schema.json`.
     *
     * @param mixed $path The path to the JSON file.
     *
     * @return PackageConfig The configuration read from the JSON file.
     *
     * @throws ConfigReaderException If the file is not found or if the data
     *                               does not match the JSON schema.
     */
    public function readConfig($path)
    {
        $reader = new JsonReader();
        $config = new PackageConfig();
        $schema = __DIR__.'/../../../../res/schema/config-schema.json';

        try {
            $jsonData = $reader->readJson($path, $schema);
        } catch (InvalidJsonException $e) {
            throw new ConfigReaderException(sprintf(
                'An error occurred while reading "%s": %s',
                $path,
                $e->getMessage()
            ), 0, $e);
        }

        $this->populateConfig($config, $jsonData);

        return $config;
    }

    /**
     * Reads root package configuration from a JSON file.
     *
     * The data in the JSON file is validated against the schema
     * `res/schema/config-schema.json`.
     *
     * @param mixed $path The path to the JSON file.
     *
     * @return RootPackageConfig The configuration read from the JSON file.
     *
     * @throws ConfigReaderException If the file is not found or if the data
     *                               does not match the JSON schema.
     */
    public function readRootConfig($path)
    {
        $reader = new JsonReader();
        $config = new RootPackageConfig();
        $schema = __DIR__.'/../../../../res/schema/config-schema.json';

        try {
            $jsonData = $reader->readJson($path, $schema);
        } catch (InvalidJsonException $e) {
            throw new ConfigReaderException(sprintf(
                'An error occurred while reading "%s": %s',
                $path,
                $e->getMessage()
            ), 0, $e);
        }

        $this->populateConfig($config, $jsonData);
        $this->populateRootConfig($config, $jsonData);

        return $config;
    }

    private function populateConfig(PackageConfig $config, \stdClass $jsonData)
    {
        $config->setPackageName($jsonData->name);

        if (isset($jsonData->resources)) {
            foreach ($jsonData->resources as $path => $relativePaths) {
                $config->addResourceDefinition(new ResourceDefinition($path, (array) $relativePaths));
            }
        }

        if (isset($jsonData->tags)) {
            foreach ((array) $jsonData->tags as $selector => $tags) {
                $config->addTagDefinition(new TagDefinition($selector, (array) $tags));
            }
        }

        if (isset($jsonData->override)) {
            $config->setOverriddenPackages((array) $jsonData->override);
        }
    }

    private function populateRootConfig(RootPackageConfig $config, \stdClass $jsonData)
    {
        if (isset($jsonData->{'package-order'})) {
            $config->setPackageOrder((array) $jsonData->{'package-order'});
        }
    }
}
