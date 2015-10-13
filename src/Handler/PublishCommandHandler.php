<?php

/*
 * This file is part of the puli/asset-plugin package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Handler;

use Puli\Manager\Api\Asset\AssetManager;
use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Installation\InstallationManager;
use Puli\Manager\Api\Installation\InstallationParams;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Api\Server\ServerManager;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Console\UI\Style\TableStyle;
use Webmozart\Expression\Expr;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PublishCommandHandler
{
    /**
     * @var AssetManager
     */
    private $assetManager;

    /**
     * @var InstallationManager
     */
    private $installationManager;

    /**
     * @var ServerManager
     */
    private $serverManager;

    /**
     * @var string
     */
    private $currentPath = '/';

    public function __construct(AssetManager $assetManager, InstallationManager $installationManager, ServerManager $serveRManager)
    {
        $this->assetManager = $assetManager;
        $this->installationManager = $installationManager;
        $this->serverManager = $serveRManager;
    }

    public function handleList(Args $args, IO $io)
    {
        /** @var AssetMapping[][] $mappingsByServer */
        $mappingsByServer = array();

        /** @var Server[] $servers */
        $servers = array();
        $nonExistingServers = array();

        // Assemble mappings and validate servers
        foreach ($this->assetManager->getAssetMappings() as $mapping) {
            $serverName = $mapping->getServerName();

            if (!isset($mappingsByServer[$serverName])) {
                $mappingsByServer[$serverName] = array();

                if ($this->serverManager->hasServer($serverName)) {
                    $servers[$serverName] = $this->serverManager->getServer($serverName);
                } else {
                    $nonExistingServers[$serverName] = true;
                }
            }

            $mappingsByServer[$serverName][] = $mapping;
        }

        if (empty($mappingsByServer)) {
            $io->writeLine('No public resources. Use "puli publish <path> <server>" to publish resources.');

            return 0;
        }

        if (count($servers) > 0) {
            $io->writeLine('The following resources are published:');
            $io->writeLine('');

            foreach ($servers as $serverName => $server) {
                $serverTitle = 'Server <bu>'.$serverName.'</bu>';

                $io->writeLine(sprintf('    <b>%s</b>', $serverTitle));
                $io->writeLine(sprintf('    Location:   <c2>%s</c2>', $server->getDocumentRoot()));
                $io->writeLine(sprintf('    Installer:  %s', $server->getInstallerName()));
                $io->writeLine(sprintf('    URL Format: <c1>%s</c1>', $server->getUrlFormat()));
                $io->writeLine('');

                $this->printMappingTable($io, $mappingsByServer[$serverName]);
                $io->writeLine('');
            }

            $io->writeLine('Use "puli publish --install" to install the resources on your servers.');
        }

        if (count($servers) > 0 && count($nonExistingServers) > 0) {
            $io->writeLine('');
        }

        if (count($nonExistingServers) > 0) {
            $io->writeLine('The following public resources are disabled since their server does not exist:');
            $io->writeLine('');

            foreach ($nonExistingServers as $serverName => $_) {
                $io->writeLine(sprintf('    <b>Server <bu>%s</bu></b>', $serverName));
                $io->writeLine('');

                $this->printMappingTable($io, $mappingsByServer[$serverName], false);
                $io->writeLine('');
            }

            $io->writeLine('Use "puli server add <name> <document-root>" to add a server.');
        }

        return 0;
    }

    public function handleAdd(Args $args)
    {
        $flags = $args->isOptionSet('force') ? AssetManager::IGNORE_SERVER_NOT_FOUND : 0;
        $path = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);

        $this->assetManager->addRootAssetMapping(new AssetMapping(
            $path,
            $args->getArgument('server'),
            $args->getArgument('server-path')
        ), $flags);

        return 0;
    }

    public function handleUpdate(Args $args)
    {
        $flags = $args->isOptionSet('force')
            ? AssetManager::OVERRIDE | AssetManager::IGNORE_SERVER_NOT_FOUND
            : AssetManager::OVERRIDE;
        $mappingToUpdate = $this->getMappingByUuidPrefix($args->getArgument('uuid'));
        $path = $mappingToUpdate->getGlob();
        $serverPath = $mappingToUpdate->getServerPath();
        $serverName = $mappingToUpdate->getServerName();

        if ($args->isOptionSet('path')) {
            $path = Path::makeAbsolute($args->getOption('path'), $this->currentPath);
        }

        if ($args->isOptionSet('server-path')) {
            $serverPath = $args->getOption('server-path');
        }

        if ($args->isOptionSet('server')) {
            $serverName = $args->getOption('server');
        }

        $updatedMapping = new AssetMapping($path, $serverName, $serverPath, $mappingToUpdate->getUuid());

        if ($this->mappingsEqual($mappingToUpdate, $updatedMapping)) {
            throw new RuntimeException('Nothing to update.');
        }

        $this->assetManager->addRootAssetMapping($updatedMapping, $flags);

        return 0;
    }

    public function handleDelete(Args $args)
    {
        $mapping = $this->getMappingByUuidPrefix($args->getArgument('uuid'));

        $this->assetManager->removeRootAssetMapping($mapping->getUuid());

        return 0;
    }

    public function handleInstall(Args $args, IO $io)
    {
        if ($args->isArgumentSet('server')) {
            $expr = Expr::method('getServerName', Expr::same($args->getArgument('server')));
            $mappings = $this->assetManager->findAssetMappings($expr);
        } else {
            $mappings = $this->assetManager->getAssetMappings();
        }

        if (!$mappings) {
            $io->writeLine('Nothing to install.');

            return 0;
        }

        /** @var InstallationParams[] $paramsToInstall */
        $paramsToInstall = array();

        // Prepare and validate the installation of all matching mappings
        foreach ($mappings as $mapping) {
            $paramsToInstall[] = $this->installationManager->prepareInstallation($mapping);
        }

        foreach ($paramsToInstall as $params) {
            foreach ($params->getResources() as $resource) {
                $serverPath = rtrim($params->getDocumentRoot(), '/').$params->getServerPathForResource($resource);

                $io->writeLine(sprintf(
                    'Installing <c1>%s</c1> into <c2>%s</c2> via <u>%s</u>...',
                    $resource->getRepositoryPath(),
                    trim($serverPath, '/'),
                    $params->getInstallerDescriptor()->getName()
                ));

                $this->installationManager->installResource($resource, $params);
            }
        }

        return 0;
    }

    /**
     * @param IO             $io
     * @param AssetMapping[] $mappings
     * @param bool           $enabled
     */
    private function printMappingTable(IO $io, array $mappings, $enabled = true)
    {
        $table = new Table(TableStyle::borderless());

        $globTag = $enabled ? 'c1' : 'bad';
        $pathTag = $enabled ? 'c2' : 'bad';

        foreach ($mappings as $mapping) {
            $uuid = substr($mapping->getUuid()->toString(), 0, 6);
            $glob = $mapping->getGlob();
            $serverPath = $mapping->getServerPath();

            if (!$enabled) {
                $uuid = sprintf('<bad>%s</bad>', $uuid);
            }

            $table->addRow(array(
                $uuid,
                sprintf('<%s>%s</%s>', $globTag, $glob, $globTag),
                sprintf('<%s>%s</%s>', $pathTag, $serverPath, $pathTag),
            ));
        }

        $table->render($io, 8);
    }

    /**
     * @param string $uuidPrefix
     *
     * @return AssetMapping
     */
    private function getMappingByUuidPrefix($uuidPrefix)
    {
        $expr = Expr::method('getUuid', Expr::startsWith($uuidPrefix));

        $mappings = $this->assetManager->findAssetMappings($expr);

        if (!$mappings) {
            throw new RuntimeException(sprintf(
                'The mapping with the UUID prefix "%s" does not exist.',
                $uuidPrefix
            ));
        }

        if (count($mappings) > 1) {
            throw new RuntimeException(sprintf(
                'More than one mapping matches the UUID prefix "%s".',
                $uuidPrefix
            ));
        }

        return reset($mappings);
    }

    private function mappingsEqual(AssetMapping $mapping1, AssetMapping $mapping2)
    {
        return $mapping1->getUuid() === $mapping2->getUuid() &&
            $mapping1->getGlob() === $mapping2->getGlob() &&
            $mapping1->getServerPath() === $mapping2->getServerPath() &&
            $mapping1->getServerName() === $mapping2->getServerName();
    }
}
