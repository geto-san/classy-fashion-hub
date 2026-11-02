<?php

namespace ClassyFashion\Providers;

use ClassyFashion\Filesystem\CloudinaryAdapter;
use ClassyFashion\Filesystem\CloudinaryFilesystem;
use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * Register durable media storage and HTTPS transactional email.
 */
class CloudStorageServiceProvider extends ServiceProvider
{
    /**
     * Resolve credentials only when their configured driver is used.
     */
    public function boot(): void
    {
        Storage::extend('cloudinary', function ($app, array $config) {
            foreach (['cloud_name', 'api_key', 'api_secret', 'cloudinary_prefix'] as $key) {
                if (empty($config[$key])) {
                    throw new \InvalidArgumentException('Missing Cloudinary setting: '.$key);
                }
            }

            $sdk = new Cloudinary([
                'cloud' => array_intersect_key($config, array_flip(['cloud_name', 'api_key', 'api_secret'])),
                'url' => ['secure' => true],
            ]);

            $adapter = new CloudinaryAdapter($sdk, $config['cloudinary_prefix'], $config['visibility']);

            return new CloudinaryFilesystem(new Filesystem($adapter, $config), $adapter, $config);
        });

        Mail::extend('brevo', function () {
            $key = config('services.brevo.key');

            if (! $key) {
                throw new \InvalidArgumentException('BREVO_API_KEY is required for HTTPS email.');
            }

            return (new BrevoTransportFactory)->create(new Dsn('brevo+api', 'default', $key));
        });
    }
}
