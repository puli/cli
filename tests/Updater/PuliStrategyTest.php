<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\tests\Updater;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_TestCase;
use Puli\Cli\PuliApplicationConfig;
use Puli\Cli\Updater\PuliStrategy;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Webmozart\Glob\Test\TestUtil;

class PuliStrategyTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PuliStrategy
     */
    private $strategy;

    public function setUp()
    {
        $this->strategy = new PuliStrategy();
    }

    public function testDownload()
    {
        $rootDir = TestUtil::makeTempDir('puli-cli', __CLASS__);
        $pharTemporaryFile = $rootDir.'/tmp.phar';

        $updaterMock = $this->getMockBuilder('Humbug\SelfUpdate\Updater')
            ->disableOriginalConstructor()
            ->getMock();

        $updaterMock->method('getTempPharFile')
            ->willReturn($pharTemporaryFile);

        $this->strategy->setStability(PuliStrategy::ANY);
        $this->strategy->download($updaterMock);

        $phpExecutableFinder = new PhpExecutableFinder();
        $pharOutput = shell_exec(sprintf(
            '%s %s',
            $phpExecutableFinder->find(),
            $pharTemporaryFile
        ));

        $this->assertRegExp('/^Puli version [a-z0-9\-\.]+/', $pharOutput);

        $filesystem = new Filesystem();
        $filesystem->remove($rootDir);
    }

    public function testGetCurrentRemoteVersion()
    {
        $updaterMock = $this->getMockBuilder('Humbug\SelfUpdate\Updater')
            ->disableOriginalConstructor()
            ->getMock();

        $this->strategy->setStability(PuliStrategy::ANY);
        $currentRemoteVersion = $this->strategy->getCurrentRemoteVersion($updaterMock);

        $this->assertNotEmpty($currentRemoteVersion);
    }

    public function testGetCurrentLocalVersion()
    {
        $updaterMock = $this->getMockBuilder('Humbug\SelfUpdate\Updater')
            ->disableOriginalConstructor()
            ->getMock();

        $currentLocalVersion = $this->strategy->getCurrentLocalVersion($updaterMock);

        $this->assertSame(PuliApplicationConfig::VERSION, $currentLocalVersion);
    }

    /**
     * @param string $args
     *
     * @dataProvider stabilitiesProvider
     */
    public function testStability($args)
    {
        $this->strategy->setStability($args);

        $property = PHPUnit_Framework_Assert::readAttribute($this->strategy, 'stability');

        $this->assertSame($args, $property);
        $this->assertSame($args, $this->strategy->getStability());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWrongStability()
    {
        $this->strategy->setStability('foo');
    }

    /**
     * @return array
     */
    public function stabilitiesProvider()
    {
        return array(
            array('stable'),
            array('unstable'),
            array('any'),
        );
    }
}
