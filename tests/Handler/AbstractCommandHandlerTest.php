<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Tests\Handler;

use PHPUnit_Framework_TestCase;
use Puli\Cli\PuliApplicationConfig;
use Puli\Manager\Api\Puli;
use Puli\Repository\Tests\TestUtil;
use Webmozart\Console\Api\Application\Application;
use Webmozart\Console\Api\Formatter\Formatter;
use Webmozart\Console\ConsoleApplication;
use Webmozart\Console\Formatter\PlainFormatter;
use Webmozart\Console\IO\BufferedIO;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractCommandHandlerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected static $tempDir;

    /**
     * @var Application
     */
    protected static $application;

    /**
     * @var Formatter
     */
    protected static $formatter;

    /**
     * @var BufferedIO
     */
    protected $io;

    public static function setUpBeforeClass()
    {
        self::$tempDir = TestUtil::makeTempDir('puli-cli', __CLASS__);
        self::$application = new ConsoleApplication(new PuliApplicationConfig(new Puli(self::$tempDir)));
        self::$formatter = new PlainFormatter(self::$application->getConfig()->getStyleSet());
    }

    protected function setUp()
    {
        $this->io = new BufferedIO('', self::$formatter);
    }
}
