The Puli Command Line Interface
===============================

[![Build Status](https://travis-ci.org/puli/cli.svg?branch=1.0.0-beta10)](https://travis-ci.org/puli/cli)
[![Build status](https://ci.appveyor.com/api/projects/status/n06gckamgc2lr8vl/branch/master?svg=true)](https://ci.appveyor.com/project/webmozart/cli/branch/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/puli/cli/badges/quality-score.png?b=1.0.0-beta10)](https://scrutinizer-ci.com/g/puli/cli/?branch=1.0.0-beta10)
[![Latest Stable Version](https://poser.pugx.org/puli/cli/v/stable.svg)](https://packagist.org/packages/puli/cli)
[![Total Downloads](https://poser.pugx.org/puli/cli/downloads.svg)](https://packagist.org/packages/puli/cli)
[![Dependency Status](https://www.versioneye.com/php/puli:cli/1.0.0/badge.svg)](https://www.versioneye.com/php/puli:cli/1.0.0)

Latest release: [1.0.0-beta10](https://packagist.org/packages/puli/cli#1.0.0-beta10)

PHP >= 5.3.9

The [Puli] Command Line Interface gives access to the [Puli Manager] via your
favorite terminal. The following is a list of the currently supported commands:

Command                              | Description
------------------------------------ | -------------
**Resource Mappings**                |
`puli map`                           | Display all path mappings
`puli map <path> <file>...`          | Map a repository path to one or several file paths
`puli map -u <path>`                 | Update the mapping for a repository path
`puli map -d <path>`                 | Delete the mapping for a repository path
**Resource Bindings**                |
`puli type`                          | Display all binding types
`puli type --define <type>`          | Define a new binding type
`puli type -d <type>`                | Remove a defined binding type
`puli bind`                          | Display all resource bindings
`puli bind <glob> <type>`            | Bind resources to a type
`puli bind -u <uuid>`                | Update a resource binding
`puli bind -d <uuid>`                | Delete a resource binding
`puli bind --enable <uuid>`          | Enable a binding of an installed package
`puli bind --disable <uuid>`         | Disable a binding of an installed package
**Public Resources**                 |
`puli publish`                       | List mapped public resources
`puli publish <path> <server>`       | Publish a resource path to a server
`puli publish -u <uuid>`             | Update a published resource
`puli publish -d <uuid>`             | Delete a published resource
`puli server`                        | List all servers
`puli server -a <name> <doc-root>`   | Add a server
`puli server -u <name>`              | Update a server
`puli server -d <name>`              | Delete a server
**Building**                         |
`puli build`                         | Build the repository and the discovery
**Packages**                         |
`puli package`                       | Display all installed packages
`puli package --add <path> <name>`   | Add a custom package
`puli package -d <name>`             | Remove an installed package
`puli package --clean`               | Remove all non-existing packages
**Configuration**                    |
`puli config`                        | Show the current configuration
`puli config -a`                     | Show the current configuration (including default values)
`puli config <key>`                  | Show the current value of a configuration key
`puli config <key> <value>`          | Change a configuration key
`puli config -d <key>`               | Remove a configuration key (reset to default)
**Repository Access**                |
`puli ls <path>`                     | List the child resources of a resource path
`puli tree <path>`                   | Print a resource and its children as tree
`puli find --name <glob>`            | Find resources matching a glob
`puli find --type <type>`            | Find resources bound to a binding type
**Plugin Management**                |
`puli plugin`                        | List the currently installed plugins
`puli plugin --install <class>`      | Install a plugin class
`puli plugin -d <class>`             | Remove a plugin class
**Update**                           |
`puli self-update`                   | Update puli.phar

Run any of the commands with the `-h` option to find out more about other
supported options.

Authors
-------

* [Bernhard Schussek] a.k.a. [@webmozart]
* [The Community Contributors]

Installation
------------

Follow the [Installation guide] guide to install Puli in your project.

Documentation
-------------

Read the [Puli Documentation] to learn more about Puli.

Contribute
----------

Contributions to are very welcome!

* Report any bugs or issues you find on the [issue tracker].
* You can grab the source code at Puli’s [Git repository].

Support
-------

If you are having problems, send a mail to bschussek@gmail.com or shout out to
[@webmozart] on Twitter.

License
-------

All contents of this package are licensed under the [MIT license].

[Puli]: http://puli.io
[Puli Manager]: https://github.com/puli/manager
[Bernhard Schussek]: http://webmozarts.com
[The Community Contributors]: https://github.com/puli/cli/graphs/contributors
[Installation guide]: http://docs.puli.io/en/latest/installation.html
[Puli Documentation]: http://docs.puli.io/en/latest/index.html
[issue tracker]: https://github.com/puli/issues/issues
[Git repository]: https://github.com/puli/cli
[@webmozart]: https://twitter.com/webmozart
[MIT license]: LICENSE
