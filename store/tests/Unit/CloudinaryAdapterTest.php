<?php

namespace Tests\Unit;

use ClassyFashion\Filesystem\CloudinaryAdapter;
use ClassyFashion\Filesystem\CloudinaryFilesystem;
use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Guard the storage operations that failed in the previously evaluated adapter.
 */
class CloudinaryAdapterTest extends TestCase
{
    /**
     * Block network traffic and provide a real SDK for deterministic URL building.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Facade::setFacadeApplication(new Container);
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }

    /**
     * Clear mocked SDK objects and facades between independent tests.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    /**
     * Preserve path and extension instead of guessing an image resource ID.
     */
    public function test_public_url_preserves_the_original_path(): void
    {
        $adapter = new CloudinaryAdapter($this->sdk(), 'classy/public');

        $this->assertSame('https://res.cloudinary.com/test-cloud/raw/upload/v1/classy/public/product/5/photo.webp', strtok($adapter->publicUrl('product/5/photo.webp', new Config), '?'));
    }

    /**
     * Bagisto calls Laravel's disk URL method rather than Flysystem directly.
     */
    public function test_laravel_disk_generates_a_working_public_url(): void
    {
        $adapter = new CloudinaryAdapter($this->sdk(), 'classy/public');
        $disk = new CloudinaryFilesystem(new Filesystem($adapter), $adapter, ['throw' => true]);

        $this->assertSame('https://res.cloudinary.com/test-cloud/raw/upload/v1/classy/public/photo.webp', strtok($disk->url('photo.webp'), '?'));
    }

    /**
     * Published images can fall back to the application's shipped assets.
     */
    public function test_laravel_disk_returns_null_for_a_missing_original(): void
    {
        Http::fake(['*' => Http::response('', 404)]);
        $adapter = new CloudinaryAdapter($this->sdk(), 'classy/public');
        $disk = new CloudinaryFilesystem(new Filesystem($adapter), $adapter, ['throw' => true]);

        $this->assertNull($disk->get('missing.png'));
    }

    /**
     * A provider outage must remain an error instead of masquerading as a missing upload.
     */
    public function test_laravel_disk_does_not_hide_provider_errors(): void
    {
        Http::fake(['*' => Http::response('', 503)]);
        $adapter = new CloudinaryAdapter($this->sdk(), 'classy/public');
        $disk = new CloudinaryFilesystem(new Filesystem($adapter), $adapter, ['throw' => true]);
        $this->expectException(UnableToReadFile::class);

        $disk->get('photo.png');
    }

    /**
     * Read the original bytes and return a usable stream for image caching.
     */
    public function test_reads_and_existence_use_the_delivery_url(): void
    {
        Http::fake(['*' => Http::response('original-image-bytes', 200)]);
        $adapter = new CloudinaryAdapter($this->sdkWithAsset(), 'classy/public');

        $this->assertTrue($adapter->fileExists('photo.webp'));
        $this->assertSame('original-image-bytes', $adapter->read('photo.webp'));
        $stream = $adapter->readStream('photo.webp');
        $this->assertSame('original-image-bytes', stream_get_contents($stream));
        fclose($stream);
    }

    /**
     * A missing asset is distinct from an authorization or provider failure.
     */
    public function test_missing_files_return_false(): void
    {
        $sdk = $this->sdkWithAsset(new NotFound('Asset not found.'));

        $this->assertFalse((new CloudinaryAdapter($sdk, 'classy/public'))->fileExists('missing.png'));
    }

    /**
     * Do not replace originals when a provider refuses an existence check.
     */
    public function test_authorization_errors_fail_existence_checks(): void
    {
        $sdk = $this->sdkWithAsset(new \RuntimeException('Provider refused authorization.'));
        $this->expectException(UnableToCheckExistence::class);

        (new CloudinaryAdapter($sdk, 'classy/public'))->fileExists('photo.png');
    }

    /**
     * Upload and delete with the same resource type, ID, and delivery type.
     */
    public function test_upload_and_delete_address_the_same_original(): void
    {
        $upload = Mockery::mock(UploadApi::class);
        $sdk = Mockery::mock(Cloudinary::class);
        $sdk->shouldReceive('uploadApi')->twice()->andReturn($upload);
        $upload->shouldReceive('upload')->once()->withArgs(function ($file, $options) {
            return file_get_contents($file) === 'original-image-bytes'
                && $options['public_id'] === 'classy/public/product/5/photo.webp'
                && $options['resource_type'] === 'raw'
                && $options['type'] === 'upload';
        })->andReturn(new ApiResponse(['public_id' => 'classy/public/product/5/photo.webp'], []));
        $upload->shouldReceive('destroy')->once()->with('classy/public/product/5/photo.webp', ['resource_type' => 'raw', 'type' => 'upload', 'invalidate' => true])->andReturn(new ApiResponse(['result' => 'ok'], []));
        $adapter = new CloudinaryAdapter($sdk, 'classy/public');

        $adapter->write('product/5/photo.webp', 'original-image-bytes', new Config);
        $adapter->delete('product/5/photo.webp');
        $this->addToAssertionCount(1);
    }

    /**
     * Private files cannot accidentally produce public URLs.
     */
    public function test_private_files_have_no_public_url(): void
    {
        $this->expectException(UnableToGeneratePublicUrl::class);

        (new CloudinaryAdapter($this->sdk(), 'classy/private', 'private'))->publicUrl('document.pdf', new Config);
    }

    /**
     * Private disks reject an upload requesting public visibility.
     */
    public function test_visibility_cannot_publish_a_private_upload(): void
    {
        $this->expectException(UnableToWriteFile::class);

        (new CloudinaryAdapter($this->sdk(), 'classy/private', 'private'))->write('document.pdf', 'private', new Config(['visibility' => 'public']));
    }

    /**
     * Whole-disk exports must continue through every Admin API page.
     */
    public function test_listing_paginates_without_changing_database_paths(): void
    {
        $admin = Mockery::mock(AdminApi::class);
        $sdk = Mockery::mock(Cloudinary::class);
        $sdk->shouldReceive('adminApi')->twice()->andReturn($admin);
        $asset = fn ($path) => ['public_id' => 'classy/public/'.$path, 'bytes' => 10, 'created_at' => '2026-10-01T00:00:00Z'];
        $admin->shouldReceive('assets')->once()->with(['resource_type' => 'raw', 'type' => 'upload', 'prefix' => 'classy/public/', 'max_results' => 500])->andReturn(new ApiResponse(['resources' => [$asset('product/a.png')], 'next_cursor' => 'page2'], []));
        $admin->shouldReceive('assets')->once()->with(['resource_type' => 'raw', 'type' => 'upload', 'prefix' => 'classy/public/', 'max_results' => 500, 'next_cursor' => 'page2'])->andReturn(new ApiResponse(['resources' => [$asset('product/b.png')]], []));
        $files = iterator_to_array((new CloudinaryAdapter($sdk, 'classy/public'))->listContents('', true));

        $this->assertSame(['product/a.png', 'product/b.png'], array_map(fn ($file) => $file->path(), $files));
    }

    /**
     * Existence uses authoritative metadata so CDN invalidation cannot hide deletions.
     */
    protected function sdkWithAsset(?\Throwable $exception = null): Cloudinary
    {
        $admin = Mockery::mock(AdminApi::class);
        $expectation = $admin->shouldReceive('asset')->once();

        if ($exception) {
            $expectation->andThrow($exception);
        } else {
            $expectation->andReturn(new ApiResponse(['public_id' => 'classy/public/photo.webp'], []));
        }

        $sdk = Mockery::mock(Cloudinary::class, [['cloud' => ['cloud_name' => 'test-cloud', 'api_key' => 'test-key', 'api_secret' => 'test-secret'], 'url' => ['secure' => true]]])->makePartial();
        $sdk->shouldReceive('adminApi')->once()->andReturn($admin);

        return $sdk;
    }

    /**
     * Test credentials are deliberately invalid and never used for network traffic.
     */
    protected function sdk(): Cloudinary
    {
        return new Cloudinary(['cloud' => ['cloud_name' => 'test-cloud', 'api_key' => 'test-key', 'api_secret' => 'test-secret'], 'url' => ['secure' => true]]);
    }
}
