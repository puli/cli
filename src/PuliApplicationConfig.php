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

use Puli\Cli\Handler\BindCommandHandler;
use Puli\Cli\Handler\BuildCommandHandler;
use Puli\Cli\Handler\CatCommandHandler;
use Puli\Cli\Handler\ConfigCommandHandler;
use Puli\Cli\Handler\FindCommandHandler;
use Puli\Cli\Handler\InstallerCommandHandler;
use Puli\Cli\Handler\LsCommandHandler;
use Puli\Cli\Handler\MapCommandHandler;
use Puli\Cli\Handler\PackageCommandHandler;
use Puli\Cli\Handler\PluginCommandHandler;
use Puli\Cli\Handler\PublishCommandHandler;
use Puli\Cli\Handler\SelfUpdateCommandHandler;
use Puli\Cli\Handler\ServerCommandHandler;
use Puli\Cli\Handler\TreeCommandHandler;
use Puli\Cli\Handler\TypeCommandHandler;
use Puli\Cli\Handler\UpgradeCommandHandler;
use Puli\Cli\Handler\UrlCommandHandler;
use Puli\Cli\Proxy\DiscoveryManagerProxy;
use Puli\Cli\Proxy\RepositoryManagerProxy;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Puli;
use Puli\Manager\Api\Server\Server;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;
use Webmozart\Console\Api\Event\ConsoleEvents;
use Webmozart\Console\Api\Event\PreHandleEvent;
use Webmozart\Console\Api\Formatter\Style;
use Webmozart\Console\Config\DefaultApplicationConfig;

/**
 * The configuration of the Puli CLI.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Stephan Wentz <swentz@brainbits.net>
 */
class PuliApplicationConfig extends DefaultApplicationConfig
{
    /**
     * The version of the Puli CLI.
     */
    const VERSION = '@package_version@';

    /**
     * @var Puli
     */
    private $puli;

    /**
     * Creates the configuration.
     *
     * @param Puli $puli The Puli service container.
     */
    public function __construct(Puli $puli = null)
    {
        // Start Puli already so that plugins can change the CLI configuration
        $this->puli = $puli ?: new Puli(getcwd());

        if (!$this->puli->isStarted()) {
            $this->puli->start();
        }

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        // Make sure the console and Puli use the same event dispatcher so that
        // Puli plugins can listen to the console events.
        // Add the dispatcher before parent::configure() so that the parent
        // listeners don't get overwritten.
        $this->setEventDispatcher($this->puli->getEventDispatcher());

        parent::configure();

        $puli = $this->puli;
        $context = $this->puli->getContext();

        $this
            ->setName('puli')
            ->setDisplayName('Puli')
            ->setVersion(self::VERSION)

            // Enable debug for unreleased versions only. Split the string to
            // prevent its replacement during release
            ->setDebug('@pack'.'age_version@' === self::VERSION)

            ->addStyle(Style::tag('good')->fgGreen())
            ->addStyle(Style::tag('bad')->fgRed())
        ;

        if ($context instanceof ProjectContext) {
            // Don't do a version check in the global context (if not in a project)
            $this->addEventListener(
                ConsoleEvents::PRE_HANDLE,
                function (PreHandleEvent $event) use ($context) {
                    $io = $event->getIO();
                    $packageFile = $context->getRootPackageFile();

                    // Don't show warning for "upgrade" command
                    if ('upgrade' === $event->getCommand()->getName()) {
                        return;
                    }

                    if (version_compare($packageFile->getVersion(), PackageFile::DEFAULT_VERSION, '<')) {
                        $io->errorLine(sprintf(
                            '<warn>Warning: Your puli.json file has version %s, '.
                            'but the latest version is %s. Run "puli upgrade" to '.
                            'upgrade to %s.</warn>',
                            $packageFile->getVersion(),
                            PackageFile::DEFAULT_VERSION,
                            PackageFile::DEFAULT_VERSION
                        ));
                    }
                }
            );
        }

        $this
            ->beginCommand('bind')
                ->setDescription('Bind resources to binding types')
                ->setHandler(function () use ($puli) {
                    return new BindCommandHandler(
                        $puli->getDiscoveryManager(),
                        $puli->getPackageManager()->getPackages()
                    );
                })

                ->beginOptionCommand('add')
                    ->markAnonymous()
                    ->addArgument('artifact', Argument::REQUIRED, 'A class name or a query for resources')
                    ->addArgument('type', Argument::REQUIRED, 'The name of the binding type')
                    ->addOption('language', null, Option::REQUIRED_VALUE, 'The language of the resource query', 'glob', 'language')
                    ->addOption('param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'A binding parameter in the form <key>=<value>', null, 'key=value')
                    ->addOption('class', null, Option::NO_VALUE, 'Force adding of a class binding')
                    ->addOption('force', 'f', Option::NO_VALUE, 'Add binding even if the binding type does not exist')
                    ->setHandlerMethod('handleAdd')
                ->end()

                ->beginOptionCommand('list')
                    ->markDefault()
                    ->addOption('root', null, Option::NO_VALUE, 'Show bindings of the root package')
                    ->addOption('package', 'p', Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Show bindings of a package', null, 'package')
                    ->addOption('all', 'a', Option::NO_VALUE, 'Show bindings of all packages')
                    ->addOption('enabled', null, Option::NO_VALUE, 'Show enabled bindings')
                    ->addOption('disabled', null, Option::NO_VALUE, 'Show disabled bindings')
                    ->addOption('type-not-found', null, Option::NO_VALUE, 'Show bindings whose type is not found')
                    ->addOption('type-not-enabled', null, Option::NO_VALUE, 'Show bindings whose type is not enabled')
                    ->addOption('ignored', null, Option::NO_VALUE, 'Show bindings whose type is disabled')
                    ->addOption('invalid', null, Option::NO_VALUE, 'Show bindings with invalid parameters')
                    ->addOption('language', null, Option::REQUIRED_VALUE, 'The language of the resource query', 'glob', 'language')
                    ->addOption('param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'A binding parameter in the form <key>=<value>', null, 'key=value')
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginOptionCommand('update', 'u')
                    ->addArgument('uuid', Argument::REQUIRED, 'The UUID (prefix) of the updated binding')
                    ->addOption('query', null, Option::REQUIRED_VALUE, 'A query for resources')
                    ->addOption('class', null, Option::REQUIRED_VALUE, 'A class name')
                    ->addOption('type', null, Option::REQUIRED_VALUE, 'The name of the binding type')
                    ->addOption('language', null, Option::REQUIRED_VALUE, 'The language of the resource query', null, 'language')
                    ->addOption('param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'A binding parameter in the form <key>=<value>', null, 'key=value')
                    ->addOption('unset-param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Unset a binding parameter', null, 'key')
                    ->addOption('force', 'f', Option::NO_VALUE, 'Update binding even if the binding type does not exist')
                    ->setHandlerMethod('handleUpdate')
                ->end()

                ->beginOptionCommand('delete', 'd')
                    ->addArgument('uuid', Argument::REQUIRED, 'The UUID (prefix) of the removed binding')
                    ->setHandlerMethod('handleDelete')
                ->end()

                ->beginOptionCommand('enable')
                    ->addArgument('uuid', Argument::REQUIRED, 'The UUID (prefix) of the enabled binding')
                    ->setHandlerMethod('handleEnable')
                ->end()

                ->beginOptionCommand('disable')
                    ->addArgument('uuid', Argument::REQUIRED, 'The UUID (prefix) of the disabled binding')
                    ->setHandlerMethod('handleDisable')
                ->end()
            ->end()
        ;

        $this
            ->beginCommand('build')
                ->setDescription('Build the resource repository/discovery')
                ->addArgument('target', Argument::OPTIONAL, 'The build target. One of "repository", "discovery", "factory" and "all"', 'all')
                ->setHandler(function () use ($puli) {
                    return new BuildCommandHandler(
                        // Use proxies to make sure this command runs even if
                        // the factory file is corrupt. If the file is corrupt,
                        // we get an error when loading the actual managers.
                        new RepositoryManagerProxy($puli),
                        new DiscoveryManagerProxy($puli),
                        $puli->getFactoryManager()
                    );
                })
            ->end()
        ;

        $this
            ->beginCommand('cat')
                ->setDescription('Concatenate a file resource in the repository')
                ->addArgument('path', Argument::REQUIRED, 'The path of a resource')
                ->setHandler(function () use ($puli) {
                    return new CatCommandHandler(
                        $puli->getRepository()
                    );
                })
            ->end()
        ;

        $this
            ->beginCommand('config')
                ->setDescription('Display and modify configuration values')
                ->setHandler(function () use ($puli) {
                    return new ConfigCommandHandler($puli->getRootPackageFileManager());
                })

                ->beginSubCommand('list')
                    ->markDefault()
                    ->addOption('parsed', null, Option::NO_VALUE, 'Replace placeholders by their values in the output')
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginSubCommand('show')
                    ->markAnonymous()
                    ->addArgument('key', Argument::REQUIRED, 'The configuration key to show. May contain wildcards ("*")')
                    ->addOption('parsed', null, Option::NO_VALUE, 'Replace placeholders by their values in the output')
                    ->setHandlerMethod('handleShow')
                ->end()

                ->beginSubCommand('set')
                    ->markAnonymous()
                    ->addArgument('key', Argument::REQUIRED, 'The modified configuration key')
                    ->addArgument('value', Argument::REQUIRED, 'The value to set for the configuration key')
                    ->setHandlerMethod('handleSet')
                ->end()

                ->beginOptionCommand('reset', 'r')
                    ->addArgument('key', Argument::REQUIRED, 'The configuration key(s) to reset. May contain wildcards ("*")')
                    ->setHandlerMethod('handleReset')
                ->end()
            ->end()
        ;

        $this
            ->beginCommand('find')
                ->setDescription('Find resources by different criteria')
                ->addOption('path', null, Option::REQUIRED_VALUE, 'The resource path. May contain the wildcard "*"')
                ->addOption('name', null, Option::REQUIRED_VALUE, 'The resource filename. May contain the wildcard "*"')
                ->addOption('class', null, Option::REQUIRED_VALUE, 'The short name of a resource class')
                ->addOption('type', null, Option::REQUIRED_VALUE, 'The name of a binding type')
                ->addOption('language', null, Option::REQUIRED_VALUE, 'The language of the query passed with --path', 'glob')
                ->setHandler(function () use ($puli) {
                    return new FindCommandHandler(
                        $puli->getRepository(),
                        $puli->getDiscovery()
                    );
                })
            ->end()
        ;

        $this
            ->beginCommand('installer')
                ->setDescription('Manage the installers used to install web resources')
                ->setHandler(function () use ($puli) {
                    return new InstallerCommandHandler($puli->getInstallerManager());
                })

                ->beginOptionCommand('list')
                    ->markDefault()
                    ->addOption('long', 'l', Option::NO_VALUE, 'Print the fully-qualified class name')
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginOptionCommand('add', 'a')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the installer')
                    ->addArgument('class', Argument::REQUIRED, 'The fully-qualified class name of the installer')
                    ->addOption('description', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'The description of the installer')
                    ->addOption('param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Additional installer parameters')
                    ->setHandlerMethod('handleAdd')
                ->end()

                ->beginOptionCommand('delete', 'd')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the installer to remove')
                    ->setHandlerMethod('handleDelete')
                ->end()
            ->end()
        ;

        $this
            ->beginCommand('ls')
                ->setDescription('List the children of a resource in the repository')
                ->addArgument('path', Argument::OPTIONAL, 'The path of a resource', '/')
                ->addOption('long', 'l', Option::NO_VALUE, 'Print more information about each child')
                ->setHandler(function () use ($puli) {
                    return new LsCommandHandler(
                        $puli->getRepository()
                    );
                })
            ->end()
        ;

        $this
            ->beginCommand('map')
                ->setDescription('Display and change path mappings')
                ->setHandler(function () use ($puli) {
                    return new MapCommandHandler(
                        $puli->getRepositoryManager(),
                        $puli->getPackageManager()->getPackages()
                    );
                })

                ->beginOptionCommand('add')
                    ->markAnonymous()
                    ->addArgument('path', Argument::REQUIRED)
                    ->addArgument('file', Argument::REQUIRED | Argument::MULTI_VALUED)
                    ->addOption('force', 'f', Option::NO_VALUE, 'Map even if the target path does not exist')
                    ->setHandlerMethod('handleAdd')
                ->end()

                ->beginOptionCommand('list')
                    ->markDefault()
                    ->addOption('root', null, Option::NO_VALUE, 'Show mappings of the root package')
                    ->addOption('package', 'p', Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Show mappings of a package', null, 'package')
                    ->addOption('all', 'a', Option::NO_VALUE, 'Show mappings of all packages')
                    ->addOption('enabled', null, Option::NO_VALUE, 'Show enabled mappings')
                    ->addOption('not-found', null, Option::NO_VALUE, 'Show mappings whose referenced paths do not exist')
                    ->addOption('conflict', null, Option::NO_VALUE, 'Show conflicting mappings')
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginOptionCommand('update', 'u')
                    ->addArgument('path', Argument::REQUIRED)
                    ->addOption('add', 'a', Option::REQUIRED_VALUE | Option::MULTI_VALUED | Option::PREFER_LONG_NAME, 'Add a file to the path mapping', null, 'file')
                    ->addOption('remove', 'r', Option::REQUIRED_VALUE | Option::MULTI_VALUED | Option::PREFER_LONG_NAME, 'Remove a file from the path mapping', null, 'file')
                    ->addOption('force', 'f', Option::NO_VALUE, 'Map even if the target path does not exist')
                    ->setHandlerMethod('handleUpdate')
                ->end()

                ->beginOptionCommand('delete', 'd')
                    ->addArgument('path', Argument::REQUIRED)
                    ->addArgument('file', Argument::OPTIONAL)
                    ->setHandlerMethod('handleDelete')
                ->end()
            ->end()
        ;

        $this
            ->beginCommand('package')
                ->setDescription('Display the installed packages')
                ->setHandler(function () use ($puli) {
                    return new PackageCommandHandler($puli->getPackageManager());
                })

                ->beginOptionCommand('install', 'i')
                    ->addArgument('path', Argument::REQUIRED, 'The path to the package')
                    ->addArgument('name', Argument::OPTIONAL, 'The name of the package. Taken from puli.json if not passed.')
                    ->addOption('installer', null, Option::REQUIRED_VALUE, 'The name of the installer', InstallInfo::DEFAULT_INSTALLER_NAME)
                    ->addOption('dev', null, Option::NO_VALUE, 'Install the package in the development environment')
                    ->setHandlerMethod('handleInstall')
                ->end()

                ->beginOptionCommand('list')
                    ->markDefault()
                    ->addOption('installer', null, Option::REQUIRED_VALUE, 'Show packages installed by a specific installer')
                    ->addOption('enabled', null, Option::NO_VALUE, 'Show enabled packages')
                    ->addOption('not-found', null, Option::NO_VALUE, 'Show packages that could not be found')
                    ->addOption('not-loadable', null, Option::NO_VALUE, 'Show packages that could not be loaded')
                    ->addOption('dev', null, Option::NO_VALUE, 'Show packages of the development environment')
                    ->addOption('prod', null, Option::NO_VALUE, 'Show packages of the production environment')
                    ->addOption('format', null, Option::REQUIRED_VALUE, 'The format of the output. You can use the variables %name%, %install_path%, %installer% and %state% in the format string', null, 'format')
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginOptionCommand('rename')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the package')
                    ->addArgument('new-name', Argument::REQUIRED, 'The new name of the package')
                    ->setHandlerMethod('handleRename')
                ->end()

                ->beginOptionCommand('delete', 'd')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the package')
                    ->setHandlerMethod('handleDelete')
                ->end()

                ->beginOptionCommand('clean')
                    ->setHandlerMethod('handleClean')
                ->end()
            ->end()
        ;

        $this
            ->beginCommand('plugin')
                ->setDescription('Manage the installed Puli plugins')
                ->setHandler(function () use ($puli) {
                    return new PluginCommandHandler($puli->getRootPackageFileManager());
                })

                ->beginOptionCommand('install', 'i')
                    ->addArgument('class', Argument::REQUIRED, 'The fully-qualified plugin class name')
                    ->setHandlerMethod('handleInstall')
                ->end()

                ->beginOptionCommand('list')
                    ->markDefault()
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginOptionCommand('delete', 'd')
                    ->addArgument('class', Argument::REQUIRED, 'The fully-qualified plugin class name')
                    ->setHandlerMethod('handleDelete')
                ->end()
            ->end()
        ;

        $this
            ->beginCommand('publish')
                ->setDescription('Manage public resources')
                ->setHandler(function () use ($puli) {
                    return new PublishCommandHandler(
                        $puli->getAssetManager(),
                        $puli->getInstallationManager(),
                        $puli->getServerManager()
                    );
                })

                ->beginOptionCommand('add')
                    ->markAnonymous()
                    ->addArgument('path', Argument::REQUIRED, 'The resource path')
                    ->addArgument('server', Argument::REQUIRED, 'The resource path')
                    ->addArgument('server-path', Argument::OPTIONAL, 'The path in the document root of the server', '/')
                    ->addOption('force', 'f', Option::NO_VALUE, 'Map even if the server does not exist')
                    ->setHandlerMethod('handleAdd')
                ->end()

                ->beginOptionCommand('update', 'u')
                    ->addArgument('uuid', Argument::REQUIRED, 'The UUID (prefix) of the mapping')
                    ->addOption('path', null, Option::REQUIRED_VALUE, 'The resource path')
                    ->addOption('server', 's', Option::REQUIRED_VALUE | Option::PREFER_LONG_NAME, 'The name of the target server')
                    ->addOption('server-path', null, Option::REQUIRED_VALUE, 'The path in the document root')
                    ->addOption('force', 'f', Option::NO_VALUE, 'Update even if the server does not exist')
                    ->setHandlerMethod('handleUpdate')
                ->end()

                ->beginOptionCommand('delete', 'd')
                    ->addArgument('uuid', Argument::REQUIRED, 'The UUID (prefix) of the mapping')
                    ->setHandlerMethod('handleDelete')
                ->end()

                ->beginOptionCommand('list', 'l')
                    ->markDefault()
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginOptionCommand('install')
                    ->addArgument('server', Argument::OPTIONAL, 'The server to install. By default, all servers are installed')
                    ->setHandlerMethod('handleInstall')
                ->end()
            ->end()
        ;

        $this
            ->beginCommand('self-update')
                ->setDescription('Update Puli to the latest version.')
                ->addOption('stable', null, Option::NO_VALUE, 'Update to the latest stable version')
                ->addOption('unstable', null, Option::NO_VALUE, 'Update to the latest unstable version')
                ->setHandler(function () {
                    return new SelfUpdateCommandHandler();
                })
                ->enableIf('phar://' === substr(__DIR__, 0, 7) && version_compare(PHP_VERSION, '5.6', '>='))
            ->end()
        ;

        $this
            ->beginCommand('server')
                ->setDescription('Manage your asset servers')
                ->setHandler(function () use ($puli) {
                    return new ServerCommandHandler($puli->getServerManager());
                })

                ->beginOptionCommand('list')
                    ->markDefault()
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginOptionCommand('add', 'a')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the added server')
                    ->addArgument('document-root', Argument::REQUIRED, 'The document root of the server')
                    ->addOption('installer', null, Option::REQUIRED_VALUE, 'The name of the used installer', 'symlink')
                    ->addOption('url-format', null, Option::REQUIRED_VALUE, 'The format of the generated resource URLs', Server::DEFAULT_URL_FORMAT)
                    ->addOption('param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Additional parameters required by the resource installer')
                    ->setHandlerMethod('handleAdd')
                ->end()

                ->beginOptionCommand('update', 'u')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the updated server')
                    ->addOption('document-root', null, Option::REQUIRED_VALUE, 'The document root of the server')
                    ->addOption('installer', null, Option::REQUIRED_VALUE, 'The name of the used installer', 'symlink')
                    ->addOption('url-format', null, Option::REQUIRED_VALUE, 'The format of the generated resource URLs', Server::DEFAULT_URL_FORMAT)
                    ->addOption('param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Additional parameters required by the resource installer')
                    ->addOption('unset-param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Parameters to remove from the server')
                    ->setHandlerMethod('handleUpdate')
                ->end()

                ->beginOptionCommand('delete', 'd')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the server to remove')
                    ->setHandlerMethod('handleDelete')
                ->end()
            ->end()
        ;

        $this
            ->beginCommand('tree')
                ->setDescription('Print the contents of a resource as tree')
                ->addArgument('path', Argument::OPTIONAL, 'The path of a resource', '/')
                ->setHandler(function () use ($puli) {
                    return new TreeCommandHandler($puli->getRepository());
                })
            ->end()
        ;

        $this
            ->beginCommand('type')
                ->setDescription('Display and change binding types')
                ->setHandler(function () use ($puli) {
                    return new TypeCommandHandler(
                        $puli->getDiscoveryManager(),
                        $puli->getPackageManager()->getPackages()
                    );
                })

                ->beginOptionCommand('define')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the binding type')
                    ->addOption('description', null, Option::REQUIRED_VALUE, 'A human-readable description of the type')
                    ->addOption('param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'A type parameter in the form <key> or <key>=<value>', null, 'key=value')
                    ->addOption('param-description', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'A human-readable parameter description in the form <key>=<description>', null, 'key=description')
                    ->addOption('force', 'f', Option::NO_VALUE, 'Add type even if another type exists with the same name')
                    ->setHandlerMethod('handleDefine')
                ->end()

                ->beginSubCommand('list')
                    ->markDefault()
                    ->addOption('root', null, Option::NO_VALUE, 'Show types of the root package')
                    ->addOption('package', 'p', Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Show types of a package', null, 'package')
                    ->addOption('all', 'a', Option::NO_VALUE, 'Show types of all packages')
                    ->addOption('enabled', null, Option::NO_VALUE, 'Show enabled types')
                    ->addOption('duplicate', null, Option::NO_VALUE, 'Show duplicate types')
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginOptionCommand('update', 'u')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the binding type')
                    ->addOption('description', null, Option::REQUIRED_VALUE, 'A human-readable description of the type')
                    ->addOption('param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'A type parameter in the form <key> or <key>=<value>', null, 'key=value')
                    ->addOption('param-description', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'A human-readable parameter description in the form <key>=<description>', null, 'key=description')
                    ->addOption('unset-param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Unset a type parameter', null, 'key')
                    ->setHandlerMethod('handleUpdate')
                ->end()

                ->beginOptionCommand('delete', 'd')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the binding type')
                    ->setHandlerMethod('handleDelete')
                ->end()
            ->end()
        ;

        $this
            ->beginCommand('upgrade')
                ->setDescription('Upgrades puli.json to the newest version')
                ->addArgument('version', Argument::OPTIONAL, 'The version to upgrade/downgrade to', PackageFile::DEFAULT_VERSION)
                ->setHandler(function () use ($puli) {
                    return new UpgradeCommandHandler($puli->getRootPackageFileManager());
                })
            ->end()
        ;

        $this
            ->beginCommand('url')
                ->setDescription('Generate the URL of a public resource')
                ->addArgument('path', Argument::REQUIRED | Argument::MULTI_VALUED, 'The path of a resource')
                ->setHandler(function () use ($puli) {
                    return new UrlCommandHandler(
                        $puli->getUrlGenerator(),
                        $puli->getRepository()
                    );
                })
            ->end()
        ;
    }
}
