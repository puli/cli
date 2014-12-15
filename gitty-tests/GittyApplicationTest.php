<?php

/*
 * This file is part of the webmozart/gitty package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\Gitty\Tests;

use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Webmozart\Gitty\Tests\Fixtures\TestApplication;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GittyApplicationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TestApplication
     */
    private $app;

    protected function setUp()
    {
        $this->app = new TestApplication(array(80, null));
        $this->app->setAutoExit(false);
        $this->app->setCatchExceptions(false);
    }

    public function getInputOutputTests()
    {
        return array(
            array('package', '"package" executed'),
            array('package arg', '"package arg" executed'),
            array('pack', '"pack" executed'),
            array('pack arg', '"pack arg" executed'),
            array('package add', '"package add" executed'),
            array('package add arg', '"package add arg" executed'),
            array('package addon', '"package addon" executed'),
            array('package addon arg', '"package addon arg" executed'),

            // valid abbreviations
            array('packa', '"package" executed'),
            array('packa arg', '"package arg" executed'),
            array('packa addo', '"package addon" executed'),
            array('packa addo arg', '"package addon arg" executed'),

            // options with simple command
            array('package -o', '"package -o" executed'),
            array('package --option', '"package -o" executed'),
            array('package -v1', '"package -v1" executed'),
            array('package -v 1', '"package -v1" executed'),
            array('package --value="1"', '"package -v1" executed'),
            array('package --value=\'1\'', '"package -v1" executed'),

            // options+args with simple command
            array('package -o arg', '"package -o arg" executed'),
            array('package --option arg', '"package -o arg" executed'),
            array('package -v1 arg', '"package -v1 arg" executed'),
            array('package -v 1 arg', '"package -v1 arg" executed'),
            array('package --value="1" arg', '"package -v1 arg" executed'),
            array('package --value=\'1\' arg', '"package -v1 arg" executed'),

            // options before sub-command not possible
            array('package -o add', '"package -o add" executed'),
            array('package --option add', '"package -o add" executed'),
            array('package -v1 add', '"package -v1 add" executed'),
            array('package -v 1 add', '"package -v1 add" executed'),
            array('package --value="1" add', '"package -v1 add" executed'),
            array('package --value=\'1\' add', '"package -v1 add" executed'),

            // options after sub-command
            array('package add -o', '"package add -o" executed'),
            array('package add --option', '"package add -o" executed'),
            array('package add -v1', '"package add -v1" executed'),
            array('package add -v 1', '"package add -v1" executed'),
            array('package add --value="1"', '"package add -v1" executed'),
            array('package add --value=\'1\'', '"package add -v1" executed'),

            // options+args after sub-command
            array('package add -o arg', '"package add -o arg" executed'),
            array('package add --option arg', '"package add -o arg" executed'),
            array('package add -v1 arg', '"package add -v1 arg" executed'),
            array('package add -v 1 arg', '"package add -v1 arg" executed'),
            array('package add --value="1" arg', '"package add -v1 arg" executed'),
            array('package add --value=\'1\' arg', '"package add -v1 arg" executed'),

            // aliases
            array('package-alias', '"package" executed'),
            array('package-alias arg', '"package arg" executed'),
            array('package add-alias', '"package add" executed'),
            array('package add-alias arg', '"package add arg" executed'),

            // aliases with options
            array('package-alias -o', '"package -o" executed'),
            array('package-alias --option', '"package -o" executed'),
            array('package-alias -v1', '"package -v1" executed'),
            array('package-alias -v 1', '"package -v1" executed'),
            array('package-alias --value="1"', '"package -v1" executed'),
            array('package-alias --value=\'1\'', '"package -v1" executed'),

            array('package-alias -o arg', '"package -o arg" executed'),
            array('package-alias --option arg', '"package -o arg" executed'),
            array('package-alias -v1 arg', '"package -v1 arg" executed'),
            array('package-alias -v 1 arg', '"package -v1 arg" executed'),
            array('package-alias --value="1" arg', '"package -v1 arg" executed'),
            array('package-alias --value=\'1\' arg', '"package -v1 arg" executed'),

            array('package add-alias -o', '"package add -o" executed'),
            array('package add-alias --option', '"package add -o" executed'),
            array('package add-alias -v1', '"package add -v1" executed'),
            array('package add-alias -v 1', '"package add -v1" executed'),
            array('package add-alias --value="1"', '"package add -v1" executed'),
            array('package add-alias --value=\'1\'', '"package add -v1" executed'),

            array('package add-alias -o arg', '"package add -o arg" executed'),
            array('package add-alias --option arg', '"package add -o arg" executed'),
            array('package add-alias -v1 arg', '"package add -v1 arg" executed'),
            array('package add-alias -v 1 arg', '"package add -v1 arg" executed'),
            array('package add-alias --value="1" arg', '"package add -v1 arg" executed'),
            array('package add-alias --value=\'1\' arg', '"package add -v1 arg" executed'),
        );
    }

    /**
     * @dataProvider getInputOutputTests
     */
    public function testRunCommand($inputString, $outputString)
    {
        $input = new StringInput($inputString);
        $output = new BufferedOutput();

        $this->app->run($input, $output);

        $this->assertSame($outputString, $output->fetch());
    }

    public function getInvalidInputs()
    {
        return array(
            array('packy', array('pack')),
            array('packy arg', array('pack')),
            array('packy add', array('pack', 'package add')),
            array('foo bar', array()),
        );
    }

    /**
     * @dataProvider getInvalidInputs
     */
    public function testRunInvalidCommand($inputString, $alternatives)
    {
        $input = new StringInput($inputString);
        $output = new BufferedOutput();

        $expectedMessage = 'is not defined';

        if (count($alternatives) > 0) {
            $expectedMessage .= '.* '.implode('\s+', $alternatives).'$';
        }

        $this->setExpectedException('\InvalidArgumentException', '~'.$expectedMessage.'~s');

        $this->app->run($input, $output);
    }

    public function getAmbiguousInputs()
    {
        return array(
            array('pac', 'pack, package'),
            array('pac ad', 'package add, package addon'),
            array('package ad', 'package add, package addon'),
        );
    }

    /**
     * @dataProvider getAmbiguousInputs
     */
    public function testRunAmbiguousCommand($inputString, $suggestions)
    {
        $input = new StringInput($inputString);
        $output = new BufferedOutput();

        $expectedMessage = 'is ambiguous';

        if (count($suggestions) > 0) {
            $expectedMessage .= '.* \('.$suggestions.'\).$';
        }

        $this->setExpectedException('\InvalidArgumentException', '~'.$expectedMessage.'~s');

        $this->app->run($input, $output);
    }

    public function getInputForCommandListAsText()
    {
        return array(
            array(''),
            array('help'),
            array('-h'),
            array('--help --text'),
        );
    }

    /**
     * @dataProvider getInputForCommandListAsText
     */
    public function testPrintCommandListAsText($inputString)
    {
        $input = new StringInput($inputString);
        $output = new BufferedOutput();

        $this->app->run($input, $output);

        $expected = <<<EOF
Test Application version 1.0.0

Usage:
 test-bin [--help] [--quiet] [--verbose] [--version] [--ansi] [--no-ansi]
          [--no-interaction] <command> [<sub-command>]

Arguments:
 <command>              The command to execute.
 <sub-command>          The sub-command to execute.

Options:
 --help (-h)            Display help about the command.
 --quiet (-q)           Do not output any message.
 --verbose              Increase the verbosity of messages: 1 for normal
                        output, 2 for more verbose output and 3 for debug.
 --version (-V)         Display this application version.
 --ansi                 Force ANSI output.
 --no-ansi              Disable ANSI output.
 --no-interaction (-n)  Do not ask any interactive question.

Available commands:
 help                   Displays help for a command
 pack                   Description of "pack"
 package                Description of "package"
 package add            Description of "package add"
 package addon          Description of "package addon"


EOF;

        $this->assertSame($expected, $output->fetch());
    }

    public function getInputForHelpUsage()
    {
        return array(
            array('help -h'),
            array('help --text help'),
            array('help help --text'),
        );
    }

    /**
     * @dataProvider getInputForHelpUsage
     */
    public function testPrintHelpUsage($inputString)
    {
        $input = new StringInput($inputString);
        $output = new BufferedOutput();

        $this->app->run($input, $output);

        $expected = <<<EOF
Usage:
 test-bin help [--man] [--ascii-doc] [--text] [--xml] [--json] [--help]
               [--quiet] [--verbose] [--version] [--ansi] [--no-ansi]
               [--no-interaction] <command-name> [<command>] [<sub-command>]

Arguments:
 <command>              The command name
 <sub-command>          The sub command name

Options:
 --man (-m)             To output help as man page
 --ascii-doc            To output help as AsciiDoc
 --text (-t)            To output help as text
 --xml (-x)             To output help as XML
 --json (-j)            To output help as JSON
 --help (-h)            Display help about the command.
 --quiet (-q)           Do not output any message.
 --verbose              Increase the verbosity of messages: 1 for normal
                        output, 2 for more verbose output and 3 for debug.
 --version (-V)         Display this application version.
 --ansi                 Force ANSI output.
 --no-ansi              Disable ANSI output.
 --no-interaction (-n)  Do not ask any interactive question.


EOF;

        $this->assertSame($expected, $output->fetch());
    }

    public function getInputForCommandUsage()
    {
        return array(
            array('package -h'),
            array('help --text package'),
            array('help package --text'),
        );
    }

    /**
     * @dataProvider getInputForCommandUsage
     */
    public function testPrintCommandUsage($inputString)
    {
        $input = new StringInput($inputString);
        $output = new BufferedOutput();

        $this->app->run($input, $output);

        $expected = <<<EOF
Usage:
 test-bin package [--option] [--value="..."] [<arg>]

Aliases: package-alias

Arguments:
 <arg>                  The "arg" argument

Options:
 --option (-o)          The "option" option
 --value (-v)           The "value" option
 --help (-h)            Display help about the command.
 --quiet (-q)           Do not output any message.
 --verbose              Increase the verbosity of messages: 1 for normal
                        output, 2 for more verbose output and 3 for debug.
 --version (-V)         Display this application version.
 --ansi                 Force ANSI output.
 --no-ansi              Disable ANSI output.
 --no-interaction (-n)  Do not ask any interactive question.


EOF;

        $this->assertSame($expected, $output->fetch());
    }

    public function getInputForCompositeCommandUsage()
    {
        return array(
            array('package add -h'),
            array('help --text package add'),
            array('help package add --text'),
        );
    }

    /**
     * @dataProvider getInputForCompositeCommandUsage
     */
    public function testPrintCompositeCommandUsage($inputString)
    {
        $input = new StringInput($inputString);
        $output = new BufferedOutput();

        $this->app->run($input, $output);

        $expected = <<<EOF
Usage:
 test-bin package add [--option] [--value="..."] [<arg>]

Aliases: package add-alias

Arguments:
 <arg>                  The "arg" argument.

Options:
 --option (-o)          The "option" option.
 --value (-v)           The "value" option.
 --help (-h)            Display help about the command.
 --quiet (-q)           Do not output any message.
 --verbose              Increase the verbosity of messages: 1 for normal
                        output, 2 for more verbose output and 3 for debug.
 --version (-V)         Display this application version.
 --ansi                 Force ANSI output.
 --no-ansi              Disable ANSI output.
 --no-interaction (-n)  Do not ask any interactive question.


EOF;

        $this->assertSame($expected, $output->fetch());
    }
}
