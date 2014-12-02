The Puli CLI
============

[![Build Status](https://travis-ci.org/puli/puli-cli.svg)](https://travis-ci.org/puli/puli-cli)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/puli/puli-cli/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/puli/puli-cli/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/c5c81ae7-451e-49bb-8b43-e8e3cd5f82f7/mini.png)](https://insight.sensiolabs.com/projects/c5c81ae7-451e-49bb-8b43-e8e3cd5f82f7)
[![Latest Stable Version](https://poser.pugx.org/puli/puli-cli/v/stable.png)](https://packagist.org/packages/puli/puli-cli)
[![Total Downloads](https://poser.pugx.org/puli/puli-cli/downloads.png)](https://packagist.org/packages/puli/puli-cli)
[![Dependency Status](https://www.versioneye.com/php/puli:puli-cli/1.0.0/badge.png)](https://www.versioneye.com/php/puli:puli-cli/1.0.0)

Latest release: none

PHP >= 5.3.9

The [Puli] CLI is a command-line interface for the [Puli Repository Manager].
The most important command is `puli dump`, which generates the resource
repository for your puli.json files as PHP file:

```
$ puli dump
```

Use `puli list` to list the contents of the repository:

```
$ puli list
$ puli list /acme/demo
```

Read [Puli at a Glance] if you want to learn more about Puli.

Authors
-------

* [Bernhard Schussek] a.k.a. [@webmozart]
* [The Community Contributors]

Installation
------------

Follow the [Getting Started] guide to install Puli in your project.

Documentation
-------------

Read the [Puli Documentation] if you want to learn more about Puli.

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

[Puli]: https://github.com/puli/puli
[Puli Repository Manager]: https://github.com/puli/puli-repository-manager
[Bernhard Schussek]: http://webmozarts.com
[The Community Contributors]: https://github.com/puli/puli-cli/graphs/contributors
[Getting Started]: http://puli.readthedocs.org/en/latest/getting-started.html
[Puli Documentation]: http://puli.readthedocs.org/en/latest/index.html
[Puli at a Glance]: http://puli.readthedocs.org/en/latest/at-a-glance.html
[issue tracker]: https://github.com/puli/puli/issues
[Git repository]: https://github.com/puli/puli-cli
[@webmozart]: https://twitter.com/webmozart
[MIT license]: LICENSE
