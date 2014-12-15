<?php

/*
 * This file is part of the webmozart/gitty package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\Gitty\Tests\Descriptor;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\ExecutableFinder;
use Webmozart\Gitty\Descriptor\DefaultDescriptor;
use Webmozart\Gitty\GittyApplication;
use Webmozart\Gitty\Process\ProcessLauncher;
use Webmozart\Gitty\Tests\Fixtures\TestPackageAddCommand;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DefaultDescriptorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ExecutableFinder
     */
    private $executableFinder;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ProcessLauncher
     */
    private $processLauncher;

    /**
     * @var DefaultDescriptor
     */
    private $descriptor;

    /**
     * @var BufferedOutput
     */
    private $output;

    /**
     * @var InputDefinition
     */
    private $inputDefinition;

    protected function setUp()
    {
        $this->executableFinder = $this->getMockBuilder('Symfony\Component\Process\ExecutableFinder')
            ->disableOriginalConstructor()
            ->getMock();
        $this->processLauncher = $this->getMockBuilder('Webmozart\Gitty\Process\ProcessLauncher')
            ->disableOriginalConstructor()
            ->getMock();

        $this->descriptor = new DefaultDescriptor($this->executableFinder, $this->processLauncher);
        $this->output = new BufferedOutput();
        $this->inputDefinition = new InputDefinition(array(
            new InputArgument('command'),
            new InputOption('man'),
            new InputOption('ascii-doc'),
            new InputOption('xml'),
            new InputOption('json'),
            new InputOption('text'),
            new InputOption('help', 'h'),
        ));
    }

    public function getInputForTextHelp()
    {
        return array(
            array('-h'),
            // "-h" overrides everything
            array('-h --xml'),
            array('--text'),
            array('--help --text'),
        );
    }

    public function getInputForXmlHelp()
    {
        return array(
            array('--xml'),
            array('--help --xml'),
        );
    }

    public function getInputForJsonHelp()
    {
        return array(
            array('--json'),
            array('--help --json'),
        );
    }

    public function getInputForManHelp()
    {
        return array(
            array('--help'),
            array('--man'),
            array('--help --man'),
        );
    }

    public function getInputForAsciiDocHelp()
    {
        return array(
            array('--ascii-doc'),
            array('--help --ascii-doc'),
        );
    }

    /**
     * @dataProvider getInputForTextHelp
     */
    public function testDescribeApplicationAsText($inputString)
    {
        $options = array(
            'input' => new StringInput($inputString, $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new GittyApplication('Test Application', '1.0.0', 'test-bin');

        $this->executableFinder->expects($this->once())
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $this->processLauncher->expects($this->never())
            ->method('launchProcess');

        $status = $this->descriptor->describe($this->output, $object, $options);

        $expected = <<<EOF
Test Application version 1.0.0

Usage:
 test-bin [--help] [--quiet] [--verbose] [--version] [--ansi] [--no-ansi] [--no-interaction] <command> [<sub-command>]

Arguments:
 <command>             The command to execute.
 <sub-command>         The sub-command to execute.

Options:
 --help (-h)           Display help about the command.
 --quiet (-q)          Do not output any message.
 --verbose (-v|vv|vvv) Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug.
 --version (-V)        Display this application version.
 --ansi                Force ANSI output.
 --no-ansi             Disable ANSI output.
 --no-interaction (-n) Do not ask any interactive question.

Available commands:
 help   Displays help for a command

EOF;


        $this->assertSame($expected, $this->output->fetch());
        $this->assertSame(0, $status);
    }

    public function testDescribeApplicationByDefault()
    {
        $this->testDescribeApplicationAsText('');
    }

    /**
     * @dataProvider getInputForXmlHelp
     */
    public function testDescribeApplicationAsXml($inputString)
    {
        $options = array(
            'input' => new StringInput($inputString, $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new GittyApplication('Test Application', '1.0.0');

        $this->executableFinder->expects($this->once())
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $this->processLauncher->expects($this->never())
            ->method('launchProcess');

        $status = $this->descriptor->describe($this->output, $object, $options);
        $output = $this->output->fetch();

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $output);
        $this->assertContains('<symfony name="Test Application" version="1.0.0">', $output);
        $this->assertSame(0, $status);
    }

    /**
     * @dataProvider getInputForJsonHelp
     */
    public function testDescribeApplicationAsJson($inputString)
    {
        $options = array(
            'input' => new StringInput($inputString, $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new GittyApplication('Test Application', '1.0.0');

        $this->executableFinder->expects($this->once())
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $this->processLauncher->expects($this->never())
            ->method('launchProcess');

        $status = $this->descriptor->describe($this->output, $object, $options);
        $output = $this->output->fetch();

        $this->assertStringStartsWith('{"commands":[', $output);
        $this->assertSame(0, $status);
    }

    /**
     * @dataProvider getInputForManHelp
     */
    public function testDescribeApplicationAsMan($inputString)
    {
        $options = array(
            'input' => new StringInput($inputString, $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new GittyApplication('Test Application', '1.0.0');

        $this->executableFinder->expects($this->once())
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $command = sprintf("man-binary -l '%s'", __DIR__.'/Fixtures/man/default-page.1');

        $this->processLauncher->expects($this->once())
            ->method('launchProcess')
            ->with($command, false)
            ->will($this->returnValue(123));

        $status = $this->descriptor->describe($this->output, $object, $options);

        $this->assertSame(123, $status);
    }

    /**
     * @dataProvider getInputForAsciiDocHelp
     */
    public function testDescribeApplicationAsAsciiDoc($inputString)
    {
        $options = array(
            'input' => new StringInput($inputString, $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new GittyApplication('Test Application', '1.0.0');

        $this->executableFinder->expects($this->at(0))
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->executableFinder->expects($this->at(1))
            ->method('find')
            ->with('less')
            ->will($this->returnValue('less-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $command = sprintf("less-binary '%s'", __DIR__.'/Fixtures/ascii-doc/default-page.txt');

        $this->processLauncher->expects($this->once())
            ->method('launchProcess')
            ->with($command, false)
            ->will($this->returnValue(123));

        $status = $this->descriptor->describe($this->output, $object, $options);

        $this->assertSame(123, $status);
    }

    public function testDescribeApplicationAsAsciiDocPrintsWhenLessNotFound()
    {
        $options = array(
            'input' => new StringInput('--ascii-doc', $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new GittyApplication('Test Application', '1.0.0');

        $this->executableFinder->expects($this->at(0))
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->executableFinder->expects($this->at(1))
            ->method('find')
            ->with('less')
            ->will($this->returnValue(false));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $this->processLauncher->expects($this->never())
            ->method('launchProcess');

        $status = $this->descriptor->describe($this->output, $object, $options);

        $this->assertSame("Contents of default-page.txt\n", $this->output->fetch());
        $this->assertSame(0, $status);
    }

    public function testDescribeApplicationAsAsciiDocPrintsWhenProcessLauncherNotSupported()
    {
        $options = array(
            'input' => new StringInput('--ascii-doc', $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new GittyApplication('Test Application', '1.0.0');

        $this->executableFinder->expects($this->at(0))
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->executableFinder->expects($this->at(1))
            ->method('find')
            ->with('less')
            ->will($this->returnValue('less-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(false));

        $this->processLauncher->expects($this->never())
            ->method('launchProcess');

        $status = $this->descriptor->describe($this->output, $object, $options);

        $this->assertSame("Contents of default-page.txt\n", $this->output->fetch());
        $this->assertSame(0, $status);
    }

    public function testDescribeApplicationAsAsciiDocWhenManBinaryNotFound()
    {
        $options = array(
            'input' => new StringInput('--help', $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new GittyApplication('Test Application', '1.0.0');

        $this->executableFinder->expects($this->at(0))
            ->method('find')
            ->with('man')
            ->will($this->returnValue(false));

        $this->executableFinder->expects($this->at(1))
            ->method('find')
            ->with('less')
            ->will($this->returnValue('less-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $command = sprintf("less-binary '%s'", __DIR__.'/Fixtures/ascii-doc/default-page.txt');

        $this->processLauncher->expects($this->once())
            ->method('launchProcess')
            ->with($command, false)
            ->will($this->returnValue(123));

        $status = $this->descriptor->describe($this->output, $object, $options);

        $this->assertSame(123, $status);
    }

    public function testDescribeApplicationAsAsciiDocWhenManPageNotFound()
    {
        $options = array(
            'input' => new StringInput('--help', $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'man-not-found',
        );

        $object = new GittyApplication('Test Application', '1.0.0');

        $this->executableFinder->expects($this->at(0))
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->executableFinder->expects($this->at(1))
            ->method('find')
            ->with('less')
            ->will($this->returnValue('less-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $command = sprintf("less-binary '%s'", __DIR__.'/Fixtures/ascii-doc/man-not-found.txt');

        $this->processLauncher->expects($this->once())
            ->method('launchProcess')
            ->with($command, false)
            ->will($this->returnValue(123));

        $status = $this->descriptor->describe($this->output, $object, $options);

        $this->assertSame(123, $status);
    }

    public function testPrintAsciiDocWhenProcessLauncherNotSupported()
    {
        $options = array(
            'input' => new StringInput('--help', $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new GittyApplication('Test Application', '1.0.0');

        $this->executableFinder->expects($this->at(0))
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->executableFinder->expects($this->at(1))
            ->method('find')
            ->with('less')
            ->will($this->returnValue('less-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(false));

        $this->processLauncher->expects($this->never())
            ->method('launchProcess');

        $status = $this->descriptor->describe($this->output, $object, $options);

        $this->assertSame("Contents of default-page.txt\n", $this->output->fetch());
        $this->assertSame(0, $status);
    }

    public function testDescribeApplicationAsTextWhenAsciiDocPageNotFound()
    {
        $options = array(
            'input' => new StringInput('--help', $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'not-found',
        );

        $object = new GittyApplication('Test Application', '1.0.0', 'test-bin');

        $this->executableFinder->expects($this->once())
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $this->processLauncher->expects($this->never())
            ->method('launchProcess');

        $status = $this->descriptor->describe($this->output, $object, $options);

        $expected = <<<EOF
Test Application version 1.0.0

Usage:
 test-bin [--help] [--quiet] [--verbose] [--version] [--ansi] [--no-ansi] [--no-interaction] <command> [<sub-command>]

Arguments:
 <command>             The command to execute.
 <sub-command>         The sub-command to execute.

Options:
 --help (-h)           Display help about the command.
 --quiet (-q)          Do not output any message.
 --verbose (-v|vv|vvv) Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug.
 --version (-V)        Display this application version.
 --ansi                Force ANSI output.
 --no-ansi             Disable ANSI output.
 --no-interaction (-n) Do not ask any interactive question.

Available commands:
 help   Displays help for a command

EOF;


        $this->assertSame($expected, $this->output->fetch());
        $this->assertSame(0, $status);
    }

    /**
     * @dataProvider getInputForTextHelp
     */
    public function testDescribeCommandAsText($inputString)
    {
        $options = array(
            'input' => new StringInput($inputString, $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new TestPackageAddCommand();

        $this->executableFinder->expects($this->once())
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $this->processLauncher->expects($this->never())
            ->method('launchProcess');

        $status = $this->descriptor->describe($this->output, $object, $options);

        $expected = <<<EOF
Usage:
 package add [--option] [--value="..."] [<arg>]

Aliases: package add-alias
Arguments:
 <arg>         The "arg" argument.

Options:
 --option (-o) The "option" option.
 --value (-v)  The "value" option.


EOF;


        $this->assertSame($expected, $this->output->fetch());
        $this->assertSame(0, $status);
    }

    /**
     * @dataProvider getInputForXmlHelp
     */
    public function testDescribeCommandAsXml($inputString)
    {
        $options = array(
            'input' => new StringInput($inputString, $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new TestPackageAddCommand();

        $this->executableFinder->expects($this->once())
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $this->processLauncher->expects($this->never())
            ->method('launchProcess');

        $status = $this->descriptor->describe($this->output, $object, $options);

        $output = $this->output->fetch();

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $output);
        $this->assertContains('<command id="package add" name="package add">', $output);
        $this->assertSame(0, $status);
    }

    /**
     * @dataProvider getInputForJsonHelp
     */
    public function testDescribeCommandAsJson($inputString)
    {
        $options = array(
            'input' => new StringInput($inputString, $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new TestPackageAddCommand();

        $this->executableFinder->expects($this->once())
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $this->processLauncher->expects($this->never())
            ->method('launchProcess');

        $status = $this->descriptor->describe($this->output, $object, $options);
        $output = $this->output->fetch();

        $this->assertStringStartsWith('{"name":"package add",', $output);
        $this->assertSame(0, $status);
    }

    /**
     * @dataProvider getInputForManHelp
     */
    public function testDescribeCommandAsMan($inputString)
    {
        $options = array(
            'input' => new StringInput($inputString, $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new TestPackageAddCommand();

        $this->executableFinder->expects($this->once())
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $command = sprintf("man-binary -l '%s'", __DIR__.'/Fixtures/man/package-add.1');

        $this->processLauncher->expects($this->once())
            ->method('launchProcess')
            ->with($command, false)
            ->will($this->returnValue(123));

        $status = $this->descriptor->describe($this->output, $object, $options);

        $this->assertSame(123, $status);
    }

    /**
     * @dataProvider getInputForAsciiDocHelp
     */
    public function testDescribeCommandAsAsciiDoc($inputString)
    {
        $options = array(
            'input' => new StringInput($inputString, $this->inputDefinition),
            'manDir' => __DIR__.'/Fixtures/man',
            'asciiDocDir' => __DIR__.'/Fixtures/ascii-doc',
            'defaultPage' => 'default-page',
        );

        $object = new TestPackageAddCommand();

        $this->executableFinder->expects($this->at(0))
            ->method('find')
            ->with('man')
            ->will($this->returnValue('man-binary'));

        $this->executableFinder->expects($this->at(1))
            ->method('find')
            ->with('less')
            ->will($this->returnValue('less-binary'));

        $this->processLauncher->expects($this->any())
            ->method('isSupported')
            ->will($this->returnValue(true));

        $command = sprintf("less-binary '%s'", __DIR__.'/Fixtures/ascii-doc/package-add.txt');

        $this->processLauncher->expects($this->once())
            ->method('launchProcess')
            ->with($command, false)
            ->will($this->returnValue(123));

        $status = $this->descriptor->describe($this->output, $object, $options);

        $this->assertSame(123, $status);
    }
}
