<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Tests\Console\Application;

use Puli\Cli\Tests\Console\Application\Fixtures\TestApplication;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompositeCommandApplicationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TestApplication
     */
    private $app;

    protected function setUp()
    {
        $this->app = new TestApplication();
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
}
