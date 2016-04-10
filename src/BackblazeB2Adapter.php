<?php

namespace Insanekitty\Flysystem\BackblazeB2;

use League\Flysystem\Util;
use League\Flysystem\Config;
use League\Flysystem\Adapter\AbstractAdapter;
use Insanekitty\BackblazeB2\Client as BackblazeB2Client;

class BackblazeB2Adapter extends AbstractAdapter
{
    const PUBLIC_GRANT_URI = 'http://acs.amazonaws.com/groups/global/AllUsers';

    /**
     * @var array
     */
    protected static $resultMap = [
        'Body'          => 'contents',
        'ContentLength' => 'size',
        'ContentType'   => 'mimetype',
        'Size'          => 'size',
    ];

    /**
     * @var array
     */
    protected static $metaOptions = [
        'CacheControl',
        'Expires',
        'StorageClass',
        'ServerSideEncryption',
        'Metadata',
        'ACL',
        'ContentType',
        'ContentEncoding',
        'ContentDisposition',
        'ContentLength',
    ];

    /**
     * @var b2Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var array
     */
    protected $options = [];

    protected $bucketId;

    protected $bucketType = 'allPublic';

    /**
     * Constructor.
     *
     * @param BackblazeB2Client $client
     * @param string   $bucket
     * @param string   $prefix
     * @param array    $options
     */
    public function __construct(BackblazeB2Client $client, $bucket = null)
    {
        $this->client = $client;
        $this->bucket = $bucket;
        //$this->setPathPrefix($prefix);
    }

    /**
     * Get the BackblazeB2Client bucket.
     *
     * @return string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * Get the BackblazeB2Client instance.
     *
     * @return BackblazeB2Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return false|array false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return false|array false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        if (!$this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $location = $this->applyPathPrefix($path);

        $command = $this->client->getCommand(
            'deleteObject',
            [
                'Bucket' => $this->bucket,
                'Key' => $location,
            ]
        );

        $this->client->execute($command);

        return ! $this->has($path);
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        try {
            $prefix = $this->applyPathPrefix($dirname) . '/';
            $this->client->deleteMatchingObjects($this->bucket, $prefix);
        } catch (DeleteMultipleObjectsException $exception) {
            return false;
        }

        return true;
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return bool|array
     */
    public function createDir($dirname, Config $config)
    {
        return $this->upload($dirname . '/', '', $config);
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return bool
     */
    public function has($path)
    {
        $location = $this->applyPathPrefix($path);

        return $this->client->doesFileExist($this->bucket, $location);
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return false|array
     */
    public function read($path)
    {

    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $files = [];

        $bucketName = $this->getBucket();
        $buckets    = $this->client->listBuckets();

        $filter = function ($bucket) {
            print_r($this->getBucket());
            if ($bucket['bucketName'] === $this->getBucket()) {
                return true;
            }
            return false;
        };
        $filter->bindTo($this);

        $bucket = array_filter($buckets, $filter);
        $bucket = current($bucket);

        if ($bucket['bucketId']) {
            $files = $this->client->listFiles($bucket['bucketId']);
        }

        // foreach ($result['contents'] as $object) {
        //     $path = $this->removePathPrefix($object['path']);
        //     $listing[] = $this->normalizeResponse($object, $path);

        //     if ($recursive && $object['is_dir']) {
        //         $listing = array_merge($listing, $this->listContents($path, true));
        //     }
        // }

        // ErrorException in /home/vagrant/larva/vendor/league/flysystem/src/Util/ContentListingFormatter.php line 64:
        // Undefined index: path
        return $files;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return false|array
     */
    public function getMetadata($path)
    {

    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return false|array
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return false|array
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return false|array
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {

    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {

    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {

    }

    /**
     * Read an object and normalize the response.
     *
     * @param $path
     *
     * @return array|bool
     */
    protected function readObject($path)
    {

    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility)
    {

    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getVisibility($path)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function applyPathPrefix($prefix)
    {
        return ltrim(parent::applyPathPrefix($prefix), '/');
    }

    /**
     * {@inheritdoc}
     */
    public function setPathPrefix($prefix)
    {
        $prefix = ltrim($prefix, '/');

        return parent::setPathPrefix($prefix);
    }

    /**
     * Get the object acl presented as a visibility.
     *
     * @param string $path
     *
     * @return string
     */
    protected function getRawVisibility($path)
    {

    }

    /**
     * Upload an object.
     *
     * @param        $path
     * @param        $body
     * @param Config $config
     *
     * @return array
     */
    protected function upload($path, $body, Config $config)
    {

    }

    /**
     * Get options from the config.
     *
     * @param Config $config
     *
     * @return array
     */
    protected function getOptionsFromConfig(Config $config)
    {

    }

    /**
     * Normalize the object result array.
     *
     * @param array  $response
     * @param string $path
     *
     * @return array
     */
    protected function normalizeResponse(array $response, $path = null)
    {

    }
}
