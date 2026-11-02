<?php

namespace ClassyFashion\Filesystem;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\RequestException;
use League\Flysystem\UnableToReadFile;

/**
 * Keep Bagisto's published-image fallback while allowing storage failures to surface.
 */
class CloudinaryFilesystem extends FilesystemAdapter
{
    /**
     * Bridge Laravel's URL method to Flysystem's public URL contract.
     */
    public function url($path)
    {
        return $this->driver->publicUrl($path);
    }

    /**
     * A missing original can fall back to a shipped asset; authorization failures cannot.
     */
    public function get($path)
    {
        try {
            return parent::get($path);
        } catch (UnableToReadFile $exception) {
            $previous = $exception->getPrevious();

            if ($previous instanceof RequestException && $previous->response->status() === 404) {
                return null;
            }

            throw $exception;
        }
    }
}
