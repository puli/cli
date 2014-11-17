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

use JsonSchema\Validator;
use Puli\Cli\Package\Config\PackageConfig;
use Puli\Cli\Package\Config\ResourceDefinition;
use Puli\Cli\Package\Config\RootPackageConfig;
use Puli\Cli\Package\Config\TagDefinition;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;

/**
 * Reads package configuration from a JSON file.
 *
 * The data in the JSON file is validated against the schema
 * `res/schema/puli-schema.json`.
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
     * `res/schema/puli-schema.json`.
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
        if (!file_exists($path)) {
            throw new ConfigReaderException(sprintf(
                'The file "%s" does not exist.',
                $path
            ));
        }

        $jsonData = $this->readJsonData($path);

        $this->validateJsonData($jsonData, $path);

        $config = new PackageConfig();

        $this->populateConfig($config, $jsonData);

        return $config;
    }

    /**
     * Reads root package configuration from a JSON file.
     *
     * The data in the JSON file is validated against the schema
     * `res/schema/puli-schema.json`.
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
        if (!file_exists($path)) {
            throw new ConfigReaderException(sprintf(
                'The file "%s" does not exist.',
                $path
            ));
        }

        $jsonData = $this->readJsonData($path);

        $this->validateJsonData($jsonData, $path);

        $config = new RootPackageConfig();

        $this->populateConfig($config, $jsonData);
        $this->populateRootConfig($config, $jsonData);

        return $config;
    }

    private function readJsonData($path)
    {
        $contents = file_get_contents($path);
        $jsonData = json_decode($contents);

        // Data could not be decoded
        if (null === $jsonData && null !== $contents) {
            $parser = new JsonParser();
            $e = $parser->lint($jsonData);

            // No idea if there's a case where this can happen
            if (!$e instanceof ParsingException) {
                throw new ConfigReaderException(sprintf(
                    'The file "%s" does not contain valid JSON.',
                    $path
                ));
            }

            throw new ConfigReaderException(sprintf(
                'The file "%s" does not contain valid JSON: %s',
                $path,
                $e->getMessage()
            ), 0, $e);
        }

        return $jsonData;
    }

    private function validateJsonData(\stdClass $jsonData, $path)
    {
        $schemaFile = __DIR__.'/../../../../res/schema/puli-schema.json';
        $schema = json_decode(file_get_contents($schemaFile));

        $validator = new Validator();
        $validator->check($jsonData, $schema);

        if (!$validator->isValid()) {
            $errors = '';

            foreach ((array) $validator->getErrors() as $error) {
                $prefix = $error['property'] ? $error['property'].': ' : '';
                $errors .= "\n".$prefix.$error['message'];
            }

            throw new ConfigReaderException(sprintf(
                "The file \"%s\" does not match the JSON schema:%s",
                $path,
                $errors
            ));
        }
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
