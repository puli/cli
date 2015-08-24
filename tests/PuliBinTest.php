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
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliBinTest extends PHPUnit_Framework_TestCase
{
    public function testRunHelp()
    {
        $phpFinder = new PhpExecutableFinder();

        if (!($php = $phpFinder->find())) {
            $this->markTestSkipped('The "php" command could not be found.');
        }

        $rootDir = Path::normalize(realpath(__DIR__.'/..'));
        $process = new Process($php.' '.$rootDir.'/bin/puli');

        $status = $process->run();
        $output = $process->getOutput();

        $this->assertSame(0, $status);
        $this->assertTrue(0 === strpos($output, 'Puli version ') || 0 === strpos($output, "Debug Mode\nPuli version "));
    }
}
