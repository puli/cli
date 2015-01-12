The Puli Command Line Interface
===============================

[![Build Status](https://travis-ci.org/puli/cli.svg?branch=master)](https://travis-ci.org/puli/cli)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/puli/cli/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/puli/cli/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/96bbb04c-f5c3-47c2-8e43-1f92d26f7c3a/mini.png)](https://insight.sensiolabs.com/projects/96bbb04c-f5c3-47c2-8e43-1f92d26f7c3a)
[![Latest Stable Version](https://poser.pugx.org/puli/cli/v/stable.svg)](https://packagist.org/packages/puli/cli)
[![Total Downloads](https://poser.pugx.org/puli/cli/downloads.svg)](https://packagist.org/packages/puli/cli)
[![Dependency Status](https://www.versioneye.com/php/puli:cli/1.0.0/badge.svg)](https://www.versioneye.com/php/puli:cli/1.0.0)

Latest release: [1.0.0-alpha1](https://packagist.org/packages/puli/cli#1.0.0-alpha1)

PHP >= 5.3.9

The [Puli] Command Line Interface gives access to the [Puli Repository Manager]
via your favorite terminal. The following is a list of the currently supported
commands:

Command                              | Description
------------------------------------ | -------------
**Resource Mappings**                |
`puli map`                           | Display all resource mappings
`puli map <path> <file>...`          | Map a repository path to one or several file paths
`puli map -d <path>`                 | Delete the mapping for a repository path
**Resource Bindings**                |
`puli type`                          | Display all binding types
`puli type define <type>`            | Define a new binding type
`puli type remove <type>`            | Remove a defined binding type
`puli bind`                          | Display all resource bindings
`puli bind <glob> <type>`            | Bind resources to a type
`puli bind -d <uuid>`                | Delete a resource binding
`puli bind --enable <uuid>`          | Enable a binding of an installed package
`puli bind --disable <uuid>`         | Disable a binding of an installed package
**Packages**                         |
`puli package`                       | Display all installed packages
`puli package install <name> <path>` | Install a custom package
`puli package remove <name>`         | Remove an installed package
**Configuration**                    |
`puli config`                        | Show the current configuration
`puli config -a`                     | Show the current configuration (including default values)
`puli config <key>`                  | Show the current value of a configuration key
`puli config <key> <value>`          | Change a configuration key
`puli config -d <key>`               | Remove a configuration key (reset to default)
**Repository Access**                |
`puli ls <path>`                     | List the child resources of a resource path
`puli tree <path>`                   | Print a resource and its children as tree
`puli find <glob>`                   | Find resources matching a glob
`puli find -b <type>`                | Find resources bound to a binding type

Run any of the commands with the `-h` option to find out more about other
supported options.

Read [Puli at a Glance] to learn more about Puli.

Authors
-------

* [Bernhard Schussek] a.k.a. [@webmozart]
* [The Community Contributors]

Installation
------------

Follow the [Getting Started] guide to install Puli in your project.

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
[Puli Repository Manager]: https://github.com/puli/repository-manager
[Bernhard Schussek]: http://webmozarts.com
[The Community Contributors]: https://github.com/puli/cli/graphs/contributors
[Getting Started]: http://docs.puli.io/en/latest/getting-started.html
[Puli Documentation]: http://docs.puli.io/en/latest/index.html
[Puli at a Glance]: http://docs.puli.io/en/latest/at-a-glance.html
[issue tracker]: https://github.com/puli/issues/issues
[Git repository]: https://github.com/puli/cli
[@webmozart]: https://twitter.com/webmozart
[MIT license]: LICENSE
