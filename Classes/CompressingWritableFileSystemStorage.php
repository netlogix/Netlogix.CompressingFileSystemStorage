<?php
declare(strict_types=1);

namespace Netlogix\CompressingFileSystemStorage;

use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\Storage\Exception;
use Neos\Flow\ResourceManagement\Storage\Exception as StorageException;
use Neos\Flow\ResourceManagement\Storage\WritableFileSystemStorage;
use Neos\Utility\Files;

class CompressingWritableFileSystemStorage extends WritableFileSystemStorage
{

    /**
     * The stream wrapper to use for compression
     *
     * @see https://www.php.net/manual/en/wrappers.compression.php
     * @var string
     */
    protected $streamWrapper = 'compress.zlib';

    public function __construct($name, array $options = [])
    {
        $this->name = $name;
        foreach ($options as $key => $value) {
            switch ($key) {
                case 'path':
                case 'streamWrapper':
                    $this->$key = $value;
                    break;
                default:
                    if ($value !== null) {
                        throw new Exception(sprintf('An unknown option "%s" was specified in the configuration of a resource CompressingWritableFileSystemStorage. Please check your settings.', $key), 1641372972);
                    }
            }
        }
    }

    public function getStreamByResource(PersistentResource $resource)
    {
        $pathAndFilename = $this->getStoragePathAndFilenameByHash($resource->getSha1());
        return (file_exists($pathAndFilename) ? fopen($this->addStreamWrapperToPath($pathAndFilename), 'rb') : false);
    }

    public function getStreamByResourcePath($relativePath)
    {
        $pathAndFilename = $this->path . ltrim($relativePath, '/');
        return (file_exists($pathAndFilename) ? fopen($this->addStreamWrapperToPath($pathAndFilename), 'r') : false);
    }

    protected function importTemporaryFile($temporaryPathAndFileName, $collectionName)
    {
        $this->fixFilePermissions($temporaryPathAndFileName);
        $fileSize = filesize($temporaryPathAndFileName);
        $sha1Hash = sha1_file($temporaryPathAndFileName);
        $md5Hash = md5_file($temporaryPathAndFileName);
        $targetPathAndFilename = $this->getStoragePathAndFilenameByHash($sha1Hash);

        if (!is_file($targetPathAndFilename)) {
            $this->moveTemporaryFileToFinalDestination(
                $temporaryPathAndFileName,
                $targetPathAndFilename
            );
        } else {
            unlink($temporaryPathAndFileName);
        }

        $resource = new PersistentResource();
        $resource->setFileSize($fileSize);
        $resource->setCollectionName($collectionName);
        $resource->setSha1($sha1Hash);
        if (method_exists(PersistentResource::class, 'setMd5')) {
            $resource->setMd5($md5Hash);
        }

        return $resource;
    }

    protected function moveTemporaryFileToFinalDestination($temporaryFile, $finalTargetPathAndFilename)
    {
        if (!file_exists(dirname($finalTargetPathAndFilename))) {
            Files::createDirectoryRecursively(dirname($finalTargetPathAndFilename));
        }
        if (copy($temporaryFile, $this->addStreamWrapperToPath($finalTargetPathAndFilename)) === false) {
            throw new StorageException(sprintf('The temporary file of the file import could not be moved to the final target "%s".', $finalTargetPathAndFilename), 1381156103);
        }
        unlink($temporaryFile);

        $this->fixFilePermissions($finalTargetPathAndFilename);
    }

    protected function addStreamWrapperToPath(string $path): string
    {
        return $this->streamWrapper . '://' . $path;
    }

}
