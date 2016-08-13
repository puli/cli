<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Tests;

use PHPUnit_Framework_TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;
use Webmozart\Glob\Test\TestUtil;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliBinTest extends PHPUnit_Framework_TestCase
{
    private static $php;

    private $rootDir;

    private $puli;

    public static function setUpBeforeClass()
    {
        $phpFinder = new PhpExecutableFinder();

        self::$php = $phpFinder->find();
    }

    protected function setUp()
    {
        if (!self::$php) {
            $this->markTestSkipped('The "php" command could not be found.');
        }

        $this->rootDir = TestUtil::makeTempDir('puli-manager', __CLASS__);
        $this->puli = Path::canonicalize(__DIR__.'/../bin/puli');

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures/root', $this->rootDir);

        // Load the package to import the "puli/public-resource" type
        $filesystem->mirror(__DIR__.'/../vendor/puli/url-generator', $this->rootDir.'/vendor/puli/url-generator');
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->rootDir);
    }

    public function testHelp()
    {
        $output = $this->runPuli('');

        $this->assertTrue(0 === strpos($output, 'Puli version ') || 0 === strpos($output, "Debug Mode\nPuli version "));
    }

    public function testMap()
    {
        $mappingExistsRegExp = '~\s/app\s+res\s~';

        $this->assertEmpty($this->runPuli('map /app res'));
        $this->assertRegExp($mappingExistsRegExp, $this->runPuli('map'));
        $this->assertRegExp('~^app\s~', $this->runPuli('ls'));
        $this->assertRegExp('~^messages.en.yml\s~', $this->runPuli('ls app'));
        $this->assertRegExp('~\s/app/messages.en.yml\s~', $this->runPuli('find --name *.yml'));
        $this->assertEmpty($this->runPuli('map -d /app'));
        $this->assertNotRegExp($mappingExistsRegExp, $this->runPuli('map'));
    }

    public function testBuildWhenInvalidFactoryClass()
    {
        $this->runPuli('map /app res');

        $factoryFile = $this->rootDir.'/'.trim($this->runPuli('config factory.in.file --parsed'));

        $this->assertFileExists($factoryFile);

        // Invalidate factory class syntax
        file_put_contents($factoryFile, file_get_contents($factoryFile, null, null, 3));

        $this->assertEmpty($this->runPuli('build'));

        // Factory was rebuilt, repository was built successfully
        $this->assertRegExp('~^app\s~', $this->runPuli('ls'));
    }

    public function testType()
    {
        $typeExistsRegExp = '~\sthor/catalog\s~';

        $this->assertEmpty($this->runPuli('type --define thor/catalog'));
        $this->assertRegExp($typeExistsRegExp, $this->runPuli('type'));
        $this->assertEmpty($this->runPuli('type -d thor/catalog'));
        $this->assertNotRegExp($typeExistsRegExp, $this->runPuli('type'));
    }

    /**
     * @depends testMap
     * @depends testType
     */
    public function testBind()
    {
        $bindingExistsRegExp = '~\s/app/\*\.yml\s+thor/catalog\s~';

        $this->runPuli('map /app res');
        $this->runPuli('type --define thor/catalog');

        $this->assertEmpty($this->runPuli('bind /app/*.yml thor/catalog'));

        $output = $this->runPuli('bind');

        $this->assertRegExp($bindingExistsRegExp, $output);
        $this->assertSame(1, preg_match('~\s(\S+)\s+/app/\*\.yml~', $output, $matches));

        $uuid = $matches[1];

        $this->assertEmpty($this->runPuli('bind -d '.$uuid));
        $this->assertNotRegExp($bindingExistsRegExp, $this->runPuli('bind'));
    }

    public function testServer()
    {
        $serverExistsRegExp = '~\slocalhost\s~';

        $this->assertEmpty($this->runPuli('server --add localhost public_html'));
        $this->assertRegExp($serverExistsRegExp, $this->runPuli('server'));
        $this->assertEmpty($this->runPuli('server -d localhost'));
        $this->assertNotRegExp($serverExistsRegExp, $this->runPuli('server'));
    }

    /**
     * @depends testServer
     */
    public function testPublish()
    {
        $assetExistsRegExp = '~\s/app/public\s+/\s~';

        $this->runPuli('build');
        $this->runPuli('map /app res');
        $this->runPuli('server --add localhost public_html');

        $this->assertEmpty($this->runPuli('publish /app/public localhost'));

        $output = $this->runPuli('publish');

        $this->assertRegExp($assetExistsRegExp, $output);
        $this->assertSame(1, preg_match('~\s(\S+)\s+/app/public~', $output, $matches));

        $uuid = $matches[1];

        $this->assertEmpty($this->runPuli('publish -d '.$uuid));
        $this->assertNotRegExp($assetExistsRegExp, $this->runPuli('publish'));
    }

    private function runPuli($command)
    {
        $php = escapeshellcmd(self::$php);
        $puli = ProcessUtils::escapeArgument($this->puli);
        $process = new Process($php.' '.$puli.' '.$command, $this->rootDir);
        $status = $process->run();
        $output = (string) $process->getOutput();

        if (0 !== $status) {
            echo $output."\n";
            echo $error = $process->getErrorOutput()."\n";

            if (false !== strstr($error, 'The passed JSON did not match the schema:') && false !== strstr($error, 'The property packages is not defined')) {
                $this->markTestSkipped('Do not skip this test when https://github.com/puli/issues/issues/198 is fixed');
            }
        }

        $this->assertSame(0, $status);

        return $output;
    }
}
