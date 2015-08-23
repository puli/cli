Changelog
=========

* 1.0.0-beta7 (@release_date@)

 * renamed `puli package --add` to `puli package --install`

* 1.0.0-beta6 (2015-08-12)

 * added `puli cat` command
 * added `puli url` command

* 1.0.0-beta5 (2015-05-29)

 * added support for building a PHAR with Box
 * added `puli self-update` command
 * added `--parsed` option to `puli config`
 * added `--format` option to `puli package`
 * added `puli package rename` command
 * added required argument `server` to `puli asset map`
 * removed puli/composer-plugin dependency
 * upgraded webmozart/path-util to 2.0
 * turned sub-commands into option commands, e.g. `puli installer add` to
   `puli installer --add`
 * renamed `puli asset` to `puli publish`
 * renamed `puli binding` to `puli bind`
 * renamed `puli path` to `puli map`
 * added `--stable` and `--unstable` options to `puli self-update` command

* 1.0.0-beta4 (2015-04-13)

 * removed `--all` option from `puli config`
 * changed `puli config` to display all user and effective values by default
 * removed `--force` option from `puli build`
 * added `--force` option to `puli bind`
 * added `--force` option to `puli type define`
 * renamed `puli config --delete|-d` to `puli config --reset|-r`
 * added recommendation when `puli map` returns no results
 * removed argument `<pattern>` from `puli find` and added `--path` option instead
 * renamed `--type` option to `--class` for `puli find`
 * renamed `--bound-to` option to `--type` for `puli find`
 * removed the `--package` option of `puli bind --enable|--disable`
 * renamed `puli bind` to `puli binding add|list|remove|enable|disable`
 * renamed `puli map` to `puli path map|list|remove`
 * added `--force` option to `puli path map`
 * added `--enabled`, `--not-found` and `--conflict` options to `puli binding [list]`
 * added `--name` option to `puli find`
 * added `puli binding update` command
 * added `puli path update` command
 * added `puli type update` command

* 1.0.0-beta3 (2015-03-19)

 * switched to webmozart/console package
 * moved command code to command handler classes
 * added tests for command handler classes
 * injected Puli's event dispatcher into the console application to let plugins
   extend the console configuration
 * renamed `puli bind` option `--duplicate` to `--overridden`
 * added "factory" target to `puli build` command
 * added `puli plugin` command

* 1.0.0-beta2 (2015-01-27)

 * updated to work with Puli components in version 1.0.0-beta2

* 1.0.0-beta (2015-01-12)

 * moved code from `Puli\Cli\Console` to `Puli\Cli`
 * added `puli map` command
 * added `puli type` command
 * added `puli type define` command
 * added `puli type remove` command
 * added `puli bind` command
 * added `puli package` command
 * added `puli package install` command
 * added `puli package remove` command
 * added `puli package clean` command
 * added `puli config` command
 * added `puli tree` command
 * added `puli find` command
 * improved `puli ls` command
 * renamed `puli dump` to `puli build`

* 1.0.0-alpha1 (2014-12-03)

 * first alpha release
