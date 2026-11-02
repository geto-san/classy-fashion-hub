<?php

namespace ClassyFashion\Filesystem;

use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Throwable;

/**
 * Store original bytes as raw assets so filenames, extensions, and reads agree.
 * Bagisto generates its own image derivatives from these originals.
 */
class CloudinaryAdapter implements FilesystemAdapter, PublicUrlGenerator
{
    /**
     * Separate public and authenticated assets with immutable disk visibility.
     */
    public function __construct(
        protected Cloudinary $cloudinary,
        protected string $prefix,
        protected string $visibility = 'public',
    ) {}

    /**
     * Preserve authorization failures rather than treating them as missing files.
     */
    public function fileExists(string $path): bool
    {
        try {
            $this->cloudinary->adminApi()->asset($this->id($path), $this->options());

            return true;
        } catch (NotFound) {
            return false;
        } catch (Throwable $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }
    }

    /**
     * Directories are virtual prefixes.
     */
    public function directoryExists(string $path): bool
    {
        foreach ($this->listContents($path, true) as $file) {
            return true;
        }

        return false;
    }

    /**
     * Upload bytes with a deterministic public ID including the original extension.
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $stream = fopen('php://temp', 'w+b');

        try {
            fwrite($stream, $contents);
            rewind($stream);
            $this->writeStream($path, $stream, $config);
        } finally {
            fclose($stream);
        }
    }

    /**
     * SDK uploads accept local temporary files; never persist uploads on Render.
     *
     * @param  resource  $contents
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $temporary = tmpfile();

        try {
            if ($config->get('visibility', $this->visibility) !== $this->visibility) {
                throw new \RuntimeException('Use the disk matching the required visibility.');
            }

            if (stream_copy_to_stream($contents, $temporary) === false) {
                throw new \RuntimeException('Cannot copy upload stream.');
            }

            $this->cloudinary->uploadApi()->upload(stream_get_meta_data($temporary)['uri'], [
                ...$this->options(),
                'public_id' => $this->id($path),
                'overwrite' => true,
                'invalidate' => true,
                'unique_filename' => false,
            ]);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, 'Cloudinary upload failed.', $exception);
        } finally {
            fclose($temporary);
        }
    }

    /**
     * Download the original through the supported delivery endpoint.
     */
    public function read(string $path): string
    {
        try {
            return Http::timeout(60)->get($this->readUrl($path))->throw()->body();
        } catch (Throwable $exception) {
            throw UnableToReadFile::fromLocation($path, 'Cloudinary read failed.', $exception);
        }
    }

    /**
     * Return a seekable stream for image processing and downloads.
     *
     * @return resource
     */
    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    /**
     * Idempotent deletion also invalidates cached delivery URLs.
     */
    public function delete(string $path): void
    {
        try {
            $response = $this->cloudinary->uploadApi()->destroy($this->id($path), [
                ...$this->options(),
                'invalidate' => true,
            ]);

            if (! in_array($response['result'], ['ok', 'not found'], true)) {
                throw new \RuntimeException('Cloudinary did not acknowledge deletion.');
            }
        } catch (Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, 'Cloudinary deletion failed.', $exception);
        }
    }

    /**
     * Delete only assets beneath the requested directory boundary.
     */
    public function deleteDirectory(string $path): void
    {
        foreach ($this->listContents($path, true) as $file) {
            $this->delete($file->path());
        }
    }

    /**
     * Cloudinary creates virtual directories when files are written.
     */
    public function createDirectory(string $path, Config $config): void {}

    /**
     * Prevent accidental publication of authenticated files.
     */
    public function setVisibility(string $path, string $visibility): void
    {
        if ($visibility !== $this->visibility) {
            throw UnableToSetVisibility::atLocation($path, 'Move the file to the appropriate disk instead.');
        }
    }

    /**
     * Report the fixed visibility of this disk.
     */
    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: $this->visibility);
    }

    /**
     * Detect the MIME type from the original bytes.
     */
    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes($path, mimeType: (new FinfoMimeTypeDetector)->detectMimeType($path, $this->read($path)));
    }

    /**
     * Read the authoritative asset creation timestamp.
     */
    public function lastModified(string $path): FileAttributes
    {
        $asset = $this->cloudinary->adminApi()->asset($this->id($path), $this->options());

        return new FileAttributes($path, lastModified: strtotime($asset['created_at']));
    }

    /**
     * Report the exact original byte length.
     */
    public function fileSize(string $path): FileAttributes
    {
        return new FileAttributes($path, fileSize: strlen($this->read($path)));
    }

    /**
     * Paginate all originals, isolating this application's disk prefix.
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = $this->id($path).'/';
        $cursor = null;

        do {
            $response = $this->cloudinary->adminApi()->assets(array_filter([
                ...$this->options(),
                'prefix' => $prefix,
                'max_results' => 500,
                'next_cursor' => $cursor,
            ]));

            foreach ($response['resources'] as $asset) {
                $relative = substr($asset['public_id'], strlen(trim($this->prefix, '/')) + 1);
                $within = substr($asset['public_id'], strlen($prefix));

                if (! $deep && str_contains($within, '/')) {
                    continue;
                }

                yield new FileAttributes($relative, $asset['bytes'], $this->visibility, strtotime($asset['created_at']));
            }

            $cursor = $response['next_cursor'] ?? null;
        } while ($cursor);
    }

    /**
     * Move only after the new original has uploaded successfully.
     */
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Throwable $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
    }

    /**
     * Copy original bytes without assuming image-specific SDK behavior.
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->write($destination, $this->read($source), $config);
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    /**
     * Public assets always use HTTPS; private assets never receive a public URL.
     */
    public function publicUrl(string $path, Config $config): string
    {
        if ($this->visibility !== 'public') {
            throw UnableToGeneratePublicUrl::noGeneratorConfigured($path, 'Private files require an authorized download.');
        }

        return $this->cloudinary->raw($this->id($path))->version(1)->toUrl();
    }

    /**
     * Keep existing database paths unchanged beneath the application prefix.
     */
    protected function id(string $path): string
    {
        return trim($this->prefix, '/').($path === '' ? '' : '/'.trim($path, '/'));
    }

    /**
     * Use authenticated delivery for every private original.
     */
    protected function options(): array
    {
        return ['resource_type' => 'raw', 'type' => $this->visibility === 'public' ? 'upload' : 'authenticated'];
    }

    /**
     * Private reads use short-lived, signed server-side download requests.
     */
    protected function readUrl(string $path): string
    {
        return $this->visibility === 'public'
            ? $this->publicUrl($path, new Config)
            : $this->cloudinary->uploadApi()->privateDownloadUrl($this->id($path), '', [
                ...$this->options(),
                'attachment' => false,
                'expires_at' => time() + 60,
            ]);
    }
}
