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

use Puli\Cli\Handler\BindHandler;
use Puli\Cli\Handler\BuildHandler;
use Puli\Cli\Handler\ConfigHandler;
use Puli\Cli\Handler\FindHandler;
use Puli\Cli\Handler\LsHandler;
use Puli\Cli\Handler\MapHandler;
use Puli\Cli\Handler\PackageHandler;
use Puli\Cli\Handler\TreeHandler;
use Puli\Cli\Handler\TypeHandler;
use Puli\RepositoryManager\Puli;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;
use Webmozart\Console\Api\Formatter\Style;
use Webmozart\Console\Config\DefaultApplicationConfig;
use Webmozart\Console\Handler\Help\HelpHandler;

/**
 * The configuration of the Puli CLI.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
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
     * @param Puli $puli The {@link Puli} instance. A new ones is created for
     *                   the current working directory if none is provided.
     */
    public function __construct(Puli $puli = null)
    {
        $this->puli = $puli ?: new Puli(getcwd());

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        // Let Puli plugins extend the CLI
        // Set the dispatcher before parent::configure() so that the parent
        // listeners are attached to the right dispatcher.
        $this->setEventDispatcher($this->puli->getEnvironment()->getEventDispatcher());

        parent::configure();

        $puli = $this->puli;
        $rootDir = __DIR__.'/..';

        $this
            ->setName('puli')
            ->setDisplayName('Puli')
            ->setVersion(self::VERSION)

            // Enable debug for unreleased versions only. Split the string to
            // prevent its replacement during release
            ->setDebug('@pack'.'age_version@' === self::VERSION)

            ->addStyle(Style::tag('good')->fgGreen())
            ->addStyle(Style::tag('bad')->fgRed())

            ->beginCommand('bind')
                ->setDescription('Bind resources to binding types')
                ->setHandler(function () use ($puli) {
                    return new BindHandler(
                        $puli->getDiscoveryManager(),
                        $puli->getPackageManager()->getPackages()
                    );
                })

                ->beginSubCommand('save')
                    ->markAnonymous()
                    ->addArgument('query', Argument::REQUIRED, 'A query for resources')
                    ->addArgument('type', Argument::REQUIRED, 'The name of the binding type')
                    ->addOption('language', null, Option::REQUIRED_VALUE, 'The language of the resource query', 'glob', 'language')
                    ->addOption('param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'A binding parameter in the form <key>=<value>', null, 'key=value')
                    ->setHandlerMethod('handleSave')
                ->end()

                ->beginOptionCommand('list', 'l')
                    ->markDefault()
                    ->addOption('root', null, Option::NO_VALUE, 'Show bindings of the root package')
                    ->addOption('package', 'p', Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Show bindings of a package', null, 'package')
                    ->addOption('all', 'a', Option::NO_VALUE, 'Show bindings of all packages')
                    ->addOption('enabled', null, Option::NO_VALUE, 'Show enabled bindings')
                    ->addOption('disabled', null, Option::NO_VALUE, 'Show disabled bindings')
                    ->addOption('undecided', null, Option::NO_VALUE, 'Show bindings that are neither enabled nor disabled')
                    ->addOption('duplicate', null, Option::NO_VALUE, 'Show duplicate bindings')
                    ->addOption('held-back', null, Option::NO_VALUE, 'Show bindings whose type is not loaded')
                    ->addOption('ignored', null, Option::NO_VALUE, 'Show bindings whose type is disabled')
                    ->addOption('invalid', null, Option::NO_VALUE, 'Show bindings with invalid parameters')
                    ->addOption('language', null, Option::REQUIRED_VALUE, 'The language of the resource query', 'glob', 'language')
                    ->addOption('param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'A binding parameter in the form <key>=<value>', null, 'key=value')
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginOptionCommand('delete', 'd')
                    ->addArgument('uuid', Argument::REQUIRED, 'The UUID (prefix) of the deleted binding')
                    ->setHandlerMethod('handleDelete')
                ->end()

                ->beginOptionCommand('enable')
                    ->addArgument('uuid', Argument::REQUIRED, 'The UUID (prefix) of the enabled binding')
                    ->addOption('package', 'p', Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Only enable bindings in the given package(s)', null, 'package')
                    ->setHandlerMethod('handleEnable')
                ->end()

                ->beginOptionCommand('disable')
                    ->addArgument('uuid', Argument::REQUIRED, 'The UUID (prefix) of the disabled binding')
                    ->addOption('package', 'p', Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Only enable bindings in the given package(s)', null, 'package')
                    ->setHandlerMethod('handleDisable')
                ->end()
            ->end()

            ->beginCommand('build')
                ->setDescription('Build the resource repository/discovery')
                ->addArgument('target', Argument::OPTIONAL, 'The build target. One of "repository", "discovery" and "all"', 'all')
                ->addOption('force', 'f', Option::NO_VALUE, 'Force building even if the repository/discovery is not empty')
                ->setHandler(function () use ($puli) {
                    return new BuildHandler(
                        $puli->getRepositoryManager(),
                        $puli->getDiscoveryManager()
                    );
                })
            ->end()

            ->beginCommand('config')
                ->setDescription('Display and modify configuration values')
                ->setHandler(function () use ($puli) {
                    return new ConfigHandler($puli->getRootPackageFileManager());
                })

                ->beginSubCommand('list')
                    ->markAnonymous()
                    ->addOption('all', 'a', Option::NO_VALUE, 'Include default values in the output')
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginSubCommand('show')
                    ->markAnonymous()
                    ->addArgument('key', Argument::REQUIRED, 'The configuration key to show. May contain wildcards ("*")')
                    ->setHandlerMethod('handleShow')
                ->end()

                ->beginSubCommand('set')
                    ->markAnonymous()
                    ->addArgument('key', Argument::REQUIRED, 'The modified configuration key')
                    ->addArgument('value', Argument::REQUIRED, 'The value to set for the configuration key')
                    ->setHandlerMethod('handleSet')
                ->end()

                ->beginOptionCommand('delete', 'd')
                    ->addArgument('key', Argument::REQUIRED, 'The configuration key to delete. May contain wildcards ("*")')
                    ->setHandlerMethod('handleDelete')
                ->end()
            ->end()

            ->beginCommand('find')
                ->setDescription('Find resources by different criteria')
                ->addArgument('pattern', Argument::OPTIONAL, 'A resource path pattern')
                ->addOption('type', 't', Option::REQUIRED_VALUE, 'The short name of a resource class')
                ->addOption('bound-to', 'b', Option::REQUIRED_VALUE, 'The name of a binding type')
                ->setHandler(function () use ($puli) {
                    return new FindHandler(
                        $puli->getEnvironment()->getRepository(),
                        $puli->getEnvironment()->getDiscovery()
                    );
                })
            ->end()

            ->editCommand('help')
                ->setHandler(function () use ($rootDir) {
                    $handler = new HelpHandler();
                    if (is_dir($rootDir.'/docs/man')) {
                        // The directory is generated by make
                        $handler->setManDir($rootDir.'/docs/man');
                    }
                    $handler->setAsciiDocDir($rootDir.'/docs');
                    $handler->setApplicationPage('puli');
                    $handler->setCommandPagePrefix('puli-');

                    return $handler;
                })
            ->end()

            ->beginCommand('ls')
                ->setDescription('List the children of a resource in the repository')
                ->addArgument('path', Argument::OPTIONAL, 'The path of a resource', '/')
                ->addOption('long', 'l', Option::NO_VALUE, 'Print more information about each child')
                ->setHandler(function () use ($puli) {
                    return new LsHandler($puli->getEnvironment()->getRepository());
                })
            ->end()

            ->beginCommand('map')
                ->setDescription('Display and change resource mappings')
                ->setHandler(function () use ($puli) {
                    return new MapHandler(
                        $puli->getRepositoryManager(),
                        $puli->getPackageManager()->getPackages()
                    );
                })

                ->beginSubCommand('save')
                    ->markAnonymous()
                    ->addArgument('path', Argument::REQUIRED)
                    ->addArgument('file', Argument::REQUIRED | Argument::MULTI_VALUED)
                    ->setHandlerMethod('handleSave')
                ->end()

                ->beginOptionCommand('list', 'l')
                    ->markDefault()
                    ->addOption('root', null, Option::NO_VALUE, 'Show mappings of the root package')
                    ->addOption('package', 'p', Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Show mappings of a package', null, 'package')
                    ->addOption('all', 'a', Option::NO_VALUE, 'Show mappings of all packages')
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginOptionCommand('delete', 'd')
                    ->addArgument('path', Argument::REQUIRED)
                    ->addArgument('file', Argument::OPTIONAL)
                    ->setHandlerMethod('handleDelete')
                ->end()
            ->end()

            ->beginCommand('package')
                ->setDescription('Display the installed packages')
                ->setHandler(function () use ($puli) {
                    return new PackageHandler($puli->getPackageManager());
                })

                ->beginSubCommand('list')
                    ->addOption('installer', null, Option::REQUIRED_VALUE, 'Show packages installed by a specific installer')
                    ->addOption('enabled', null, Option::NO_VALUE, 'Show enabled packages')
                    ->addOption('not-found', null, Option::NO_VALUE, 'Show packages that could not be found')
                    ->addOption('not-loadable', null, Option::NO_VALUE, 'Show packages that could not be loaded')
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginSubCommand('install')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the package')
                    ->addArgument('path', Argument::REQUIRED, 'The path to the package')
                    ->addOption('installer', null, Option::REQUIRED_VALUE, 'The name of the installer', 'user')
                    ->setHandlerMethod('handleInstall')
                ->end()

                ->beginSubCommand('remove')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the package')
                    ->setHandlerMethod('handleRemove')
                ->end()

                ->beginSubCommand('clean')
                    ->setHandlerMethod('handleClean')
                ->end()
            ->end()

            ->beginCommand('tree')
                ->setDescription('Print the contents of a resource as tree')
                ->addArgument('path', Argument::OPTIONAL, 'The path of a resource', '/')
                ->setHandler(function () use ($puli) {
                    return new TreeHandler($puli->getEnvironment()->getRepository());
                })
            ->end()

            ->beginCommand('type')
                ->setDescription('Display and change binding types')
                ->setHandler(function () use ($puli) {
                    return new TypeHandler(
                        $puli->getDiscoveryManager(),
                        $puli->getPackageManager()
                    );
                })

                ->beginSubCommand('list')
                    ->markDefault()
                    ->addOption('root', null, Option::NO_VALUE, 'Show types of the root package')
                    ->addOption('package', 'p', Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'Show types of a package', null, 'package')
                    ->addOption('all', 'a', Option::NO_VALUE, 'Show types of all packages')
                    ->addOption('enabled', null, Option::NO_VALUE, 'Show enabled types')
                    ->addOption('duplicate', null, Option::NO_VALUE, 'Show duplicate types')
                    ->setHandlerMethod('handleList')
                ->end()

                ->beginSubCommand('define')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the binding type')
                    ->addOption('description', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'A human-readable description')
                    ->addOption('param', null, Option::REQUIRED_VALUE | Option::MULTI_VALUED, 'A type parameter in the form <key> or <key>=<value>', null, 'key=value')
                    ->setHandlerMethod('handleDefine')
                ->end()

                ->beginSubCommand('remove')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the binding type')
                    ->setHandlerMethod('handleRemove')
                ->end()
            ->end()
        ;
    }
}
