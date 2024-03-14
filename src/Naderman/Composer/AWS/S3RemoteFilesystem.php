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
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;
use Psr\Http\Message\StreamInterface;

/**
 * Composer Plugin for AWS functionality
 *
 * @author Nils Adermann <naderman@naderman.de>
 */
class S3RemoteFilesystem extends RemoteFilesystem
{
    /**
     * @var AwsClient
     */
    protected $awsClient;

    protected array $lastMetadata = [];

    /**
     * {@inheritDoc}
     */
    public function __construct(IOInterface $io, Config $config = null, array $options, AwsClient $awsClient)
    {
        parent::__construct($io, $config, $options);
        $this->awsClient = $awsClient;
    }

    /**
     * {@inheritDoc}
     */
    public function getContents($originUrl, $fileUrl, $progress = true, $options = [])
    {
        $result = $this->awsClient->download($fileUrl, $progress);

        $this->lastMetadata = $result['@metadata'];
        
        $body = $result['Body'];

        return $body instanceof StreamInterface ? $body->getContents() : $body;
    }

    /**
     * {@inheritDoc}
     */
    public function copy($originUrl, $fileUrl, $fileName, $progress = true, $options = [])
    {
        $result = $this->awsClient->download($fileUrl, $progress, $fileName);

        $this->lastMetadata = $result['@metadata'];
    }

    public function getLastHeaders()
    {
        return array_merge(
            [
                sprintf('HTTP %s', $this->lastMetadata['statusCode'])
            ],
            array_map(
                fn ($header, $value) => sprintf('%s:%s', $header, $value),
                array_keys($this->lastMetadata['headers'] ?? []),
                $this->lastMetadata['headers'] ?? [],
            ),
        );
    }
}
