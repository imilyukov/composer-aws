<?php
/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 * Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\Naderman\Composer\AWS;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\HttpDownloader;
use Composer\Util\RemoteFilesystem;
use Naderman\Composer\AWS\AwsPlugin;
use Naderman\Composer\AWS\S3RemoteFilesystem;

/**
 * Composer Plugin tests for AWS functionality
 */
class AwsPluginTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Composer
     */
    protected $composer;
    
    /**
     * @var IOInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $io;
    
    public function setUp(): void
    {
        $this->composer = new Composer();
        $this->composer->setConfig(new Config());

        $this->io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
    }

    /**
     * Non-S3 addresses data provider
     * 
     * @return array
     */
    public function getNonS3Addresses()
    {
        return [
            ['http://example.com'],
            ['https://example.com'],
            ['http://example.com/packages.json'],
            ['https://example.com/packages.json']
        ];
    }

    /**
     * S3 addresses data provider
     * 
     * @return array
     */
    public function getS3Addresses()
    {
        return [
            ['s3://example.com'],
            ['s3://example'],
            ['s3://example.com/packages.json'],
            ['s3://example/packages.json']
        ];
    }
    
    /**
     * @dataProvider getNonS3Addresses
     * @param $address
     */
    public function testPluginIgnoresNonS3Protocols($address)
    {
        $plugin = new AwsPlugin();
        $plugin->activate($this->composer, $this->io);
        $event = $this->getMockBuilder(PreFileDownloadEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $event->expects($this->once())
            ->method('getProcessedUrl')
            ->willReturn($address);
        
        $plugin->onPreFileDownload($event);
    }

    /**
     * @dataProvider getS3Addresses
     * @param $address
     */
    public function testPluginReplaceRemoteSystemForS3Protocols($address)
    {
        $plugin = new AwsPlugin();
        $plugin->activate($this->composer, $this->io);
        $event = $this->getMockBuilder(PreFileDownloadEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->once())
            ->method('getProcessedUrl')
            ->willReturn($address);

        $remoteFileSystem = $this->getMockBuilder(RemoteFilesystem::class)
            ->disableOriginalConstructor()
            ->getMock();

        $remoteFileSystem->expects($this->once())
            ->method('getOptions')
            ->willReturn([]);

        $httpDownloader = $this->getMockBuilder(HttpDownloader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->getAccessor($httpDownloader)('rfs', $remoteFileSystem);

        $event->expects($this->once())
            ->method('getHttpDownloader')
            ->willReturn($httpDownloader);

       $plugin->onPreFileDownload($event);
    }

    public function getAccessor($context)
    {
        return \Closure::bind(
            function ($context) {
                return fn (string $property, mixed $value = null) => $value === null 
                    ? $context->{$property} 
                    : $context->{$property} = $value;
            },
            $context,
            get_class($context)
        )($context);
    }
}
