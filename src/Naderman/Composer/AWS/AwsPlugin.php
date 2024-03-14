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

namespace Naderman\Composer\AWS;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\Filesystem;
use Composer\Util\HttpDownloader;
use Composer\Util\RemoteFilesystem;

/**
 * Composer Plugin for AWS functionality
 *
 * @author Nils Adermann <naderman@naderman.de>
 */
class AwsPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var  Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @var AwsClient
     */
    protected $client;
    
    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Remove any hooks from Composer
     *
     * This will be called when a plugin is deactivated before being
     * uninstalled, but also before it gets upgraded to a new version
     * so the old one can be deactivated and the new one activated.
     *
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    /**
     * Prepare the plugin to be uninstalled
     *
     * This will be called after deactivate.
     *
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return array(
            PluginEvents::PRE_FILE_DOWNLOAD => array(
                array('onPreFileDownload', 0)
            ),
        );
    }

    /**
     * @return AwsClient
     */
    public function getClient()
    {
        if (is_null($this->client)) {
            $this->setClient(new AwsClient($this->io, $this->composer->getConfig()));
        }
        
        return $this->client;
    }

    /**
     * @param AwsClient $client
     */
    public function setClient(AwsClient $client)
    {
        $this->client = $client;
    }

    /**
     * Replace remote file system on S3 protocol download
     * 
     * @param PreFileDownloadEvent $event
     */
    public function onPreFileDownload(PreFileDownloadEvent $event)
    {
        $protocol = parse_url($event->getProcessedUrl(), PHP_URL_SCHEME);
        if ($protocol === 's3') {
            $httpDownloader = $event->getHttpDownloader();
            $httpDownloaderAccessor = $this->getAccessor($httpDownloader);
            /** @var RemoteFilesystem $remoteFilesystem */
            $remoteFilesystem = $httpDownloaderAccessor('rfs');

            $s3RemoteFilesystem = new S3RemoteFilesystem(
                $this->io,
                $this->composer->getConfig(),
                $remoteFilesystem->getOptions(),
                $this->getClient()
            );

            $httpDownloaderAccessor('rfs', $s3RemoteFilesystem);
        }
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
