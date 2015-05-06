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

use Puli\Cli\Util\StringUtil;
use Puli\Manager\Api\Server\NoSuchServerException;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Api\Server\ServerManager;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Console\UI\Style\TableStyle;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ServerCommandHandler
{
    /**
     * @var ServerManager
     */
    private $serverManager;

    public function __construct(ServerManager $serverManager)
    {
        $this->serverManager = $serverManager;
    }

    public function handleList(Args $args, IO $io)
    {
        $table = new Table(TableStyle::borderless());
        $servers = $this->serverManager->getServers();

        if ($servers->isEmpty()) {
            $io->writeLine('No servers. Use "puli server add <name> <document-root>" to add a server.');

            return 0;
        }

        $defaultServer = $servers->getDefaultServer();

        foreach ($servers as $server) {
            $parameters = '';

            foreach ($server->getParameterValues() as $name => $value) {
                $parameters .= "\n<c1>".$name.'='.StringUtil::formatValue($value).'</c1>';
            }

            $table->addRow(array(
                $defaultServer === $server ? '*' : '',
                '<u>'.$server->getName().'</u>',
                $server->getInstallerName(),
                '<c2>'.$server->getDocumentRoot().'</c2>'.$parameters,
                '<c1>'.$server->getUrlFormat().'</c1>'
            ));
        }

        $table->render($io);

        return 0;
    }

    public function handleAdd(Args $args)
    {
        $parameters = array();

        $this->parseParams($args, $parameters);

        $this->serverManager->addServer(new Server(
            $args->getArgument('name'),
            $args->getOption('installer'),
            $args->getArgument('document-root'),
            $args->getOption('url-format'),
            $parameters
        ));

        return 0;
    }

    public function handleUpdate(Args $args)
    {
        $serverName = $args->getArgument('name');

        if (!$this->serverManager->hasServer($serverName)) {
            throw NoSuchServerException::forServerName($serverName);
        }

        $serverToUpdate = $this->serverManager->getServer($serverName);

        $installerName = $serverToUpdate->getInstallerName();
        $documentRoot = $serverToUpdate->getDocumentRoot();
        $urlFormat = $serverToUpdate->getUrlFormat();
        $parameters = $serverToUpdate->getParameterValues();

        if ($args->isOptionSet('installer')) {
            $installerName = $args->getOption('installer');
        }

        if ($args->isOptionSet('document-root')) {
            $documentRoot = $args->getOption('document-root');
        }

        if ($args->isOptionSet('url-format')) {
            $urlFormat = $args->getOption('url-format');
        }

        $this->parseParams($args, $parameters);
        $this->unsetParams($args, $parameters);

        $updatedServer = new Server($serverName, $installerName, $documentRoot, $urlFormat, $parameters);

        if ($this->serversEqual($serverToUpdate, $updatedServer)) {
            throw new RuntimeException('Nothing to update.');
        }

        $this->serverManager->addServer($updatedServer);

        return 0;
    }

    public function handleRemove(Args $args)
    {
        $serverName = $args->getArgument('name');

        if (!$this->serverManager->hasServer($serverName)) {
            throw NoSuchServerException::forServerName($serverName);
        }

        $this->serverManager->removeServer($serverName);

        return 0;
    }

    public function handleSetDefault(Args $args)
    {
        $this->serverManager->setDefaultServer($args->getArgument('name'));

        return 0;
    }

    public function handleGetDefault(Args $args, IO $io)
    {
        $io->writeLine($this->serverManager->getDefaultServer()->getName());

        return 0;
    }

    private function parseParams(Args $args, array &$parameters)
    {
        foreach ($args->getOption('param') as $parameter) {
            $pos = strpos($parameter, '=');

            if (false === $pos) {
                throw new RuntimeException(sprintf(
                    'Invalid parameter "%s". Expected "<name>=<value>".',
                    $parameter
                ));
            }

            $parameters[substr($parameter, 0, $pos)] = StringUtil::parseValue(substr($parameter, $pos + 1));
        }
    }

    private function unsetParams(Args $args, array &$parameters)
    {
        foreach ($args->getOption('unset-param') as $parameter) {
            unset($parameters[$parameter]);
        }
    }

    private function serversEqual(Server $server1, Server $server2)
    {
        if ($server1->getName() !== $server2->getName()) {
            return false;
        }

        if ($server1->getInstallerName() !== $server2->getInstallerName()) {
            return false;
        }

        if ($server1->getDocumentRoot() !== $server2->getDocumentRoot()) {
            return false;
        }

        if ($server1->getUrlFormat() !== $server2->getUrlFormat()) {
            return false;
        }

        $parameters1 = $server1->getParameterValues();
        $parameters2 = $server2->getParameterValues();

        ksort($parameters1);
        ksort($parameters2);

        if ($parameters1 !== $parameters2) {
            return false;
        }

        return true;
    }
}
