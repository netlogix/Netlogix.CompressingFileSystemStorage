<?php

declare(strict_types=1);

namespace Netlogix\CompressingFileSystemStorage\Tests\Functional;

use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Tests\FunctionalTestCase;

class CompressingWritableFileSystemStorageTest extends FunctionalTestCase
{

    private const FILE_IN = __DIR__ . '/../Fixtures/in';
    private const FILES_OUT = [
        'compress.zlib' => __DIR__ . '/../Fixtures/out_zlib',
        'compress.bzip2' => __DIR__ . '/../Fixtures/out_bzip2',
    ];
    private const COLLECTION_NAME_ZLIB = 'nlxCompressingStorage_zlib';
    private const COLLECTION_NAME_BZIP2 = 'nlxCompressingStorage_bzip2';

    protected static $testablePersistenceEnabled = true;

    private ResourceManager $resourceManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->resourceManager = $this->objectManager->get(ResourceManager::class);
    }

    /**
     * @test
     * @dataProvider provideCollectionNames
     */
    public function sha1_of_the_original_file_is_kept(string $streamWrapper, string $collectionName): void
    {
        $this->skipIfStreamWrapperIsMissing($streamWrapper);

        $resource = $this->importResource($collectionName);
        self::assertEquals('058db10eaa5ecebcf8882e5e585cc9c4d34dcd82', $resource->getSha1());
        $this->resourceManager->deleteResource($resource);
    }

    /**
     * @test
     * @dataProvider provideCollectionNames
     */
    public function md5_of_the_original_file_is_kept(string $streamWrapper, string $collectionName): void
    {
        $this->skipIfStreamWrapperIsMissing($streamWrapper);

        $resource = $this->importResource($collectionName);
        self::assertEquals('c30d856f939dbae76309386a5ee481f7', $resource->getMd5());
        $this->resourceManager->deleteResource($resource);
    }

    /**
     * @test
     * @dataProvider provideCollectionNames
     */
    public function size_of_the_original_file_is_kept(string $streamWrapper, string $collectionName): void
    {
        $this->skipIfStreamWrapperIsMissing($streamWrapper);

        $resource = $this->importResource($collectionName);
        self::assertEquals(592, $resource->getFileSize());
        $this->resourceManager->deleteResource($resource);
    }

    /**
     * @test
     * @dataProvider provideCollectionNames
     */
    public function stream_wrapper_is_added_to_stream_uri(string $streamWrapper, string $collectionName): void
    {
        $this->skipIfStreamWrapperIsMissing($streamWrapper);

        $resource = $this->importResource($collectionName);

        $stream = $resource->getStream();
        $uri = stream_get_meta_data($stream)['uri'] ?? '';
        fclose($stream);

        self::assertStringStartsWith($streamWrapper . '://', $uri);
        $this->resourceManager->deleteResource($resource);
    }

    /**
     * @test
     * @dataProvider provideCollectionNames
     */
    public function stream_content_is_not_compressed(string $streamWrapper, string $collectionName): void
    {
        $this->skipIfStreamWrapperIsMissing($streamWrapper);

        $resource = $this->importResource($collectionName);

        $stream = $resource->getStream();
        $contents = stream_get_contents($stream);

        self::assertEquals(file_get_contents(self::FILE_IN), $contents);
        fclose($stream);
        $this->resourceManager->deleteResource($resource);
    }

    /**
     * @test
     * @dataProvider provideCollectionNames
     */
    public function temporary_local_copy_is_not_compressed(string $streamWrapper, string $collectionName): void
    {
        $this->skipIfStreamWrapperIsMissing($streamWrapper);

        $resource = $this->importResource($collectionName);

        $uri = $resource->createTemporaryLocalCopy();

        self::assertFileEquals(self::FILE_IN, $uri);
        $this->resourceManager->deleteResource($resource);
    }

    /**
     * FIXME: This should not rely on stream URI
     * FIXME: This should not rely on exec for determining file compression type
     *
     * @test
     * @dataProvider provideCollectionNames
     */
    public function stored_file_is_compressed(string $streamWrapper, string $collectionName): void
    {
        $this->skipIfStreamWrapperIsMissing($streamWrapper);

        $resource = $this->importResource($collectionName);

        $stream = $resource->getStream();
        $uri = stream_get_meta_data($stream)['uri'] ?? '';
        fclose($stream);
        $streamWrapperPrefix = $streamWrapper . '://';

        $path = substr($uri, strlen($streamWrapperPrefix));

        $output = exec(sprintf('file %s', escapeshellarg($path)));
        if ($output === false) {
            $this->markTestSkipped('Could not run file command');
        }

        if ($streamWrapper === 'compress.zlib') {
            self::assertStringContainsString('gzip', $output);
        } elseif ($streamWrapper === 'compress.bzip2') {
            self::assertStringContainsString('bzip2', $output);
        } else {
            $this->fail('No expectation for file compression was given');
        }

        $this->resourceManager->deleteResource($resource);
    }

    public function provideCollectionNames(): iterable
    {
        yield 'zlib' => [
            'streamWrapper' => 'compress.zlib',
            'collectionName' => self::COLLECTION_NAME_ZLIB,
        ];
        yield 'bzip2' => [
            'streamWrapper' => 'compress.bzip2',
            'collectionName' => self::COLLECTION_NAME_BZIP2
        ];
    }

    private function importResource(string $collectionName): PersistentResource
    {
        return $this->resourceManager->importResource(self::FILE_IN, $collectionName);
    }

    private function skipIfStreamWrapperIsMissing(string $streamWrapper)
    {
        if (!in_array($streamWrapper, stream_get_wrappers(), true)) {
            $this->markTestSkipped(sprintf('Stream Wrapper "%s" is missing', $streamWrapper));
        }
    }

}
