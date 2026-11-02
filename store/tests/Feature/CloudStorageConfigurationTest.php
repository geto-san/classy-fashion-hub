<?php

use ClassyFashion\Filesystem\CloudinaryFilesystem;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToGeneratePublicUrl;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

beforeEach(function () {
    config(['filesystems.disks.cloud_contract' => [
        'driver' => 'cloudinary',
        'cloud_name' => 'contract-cloud',
        'api_key' => 'contract-key',
        'api_secret' => 'contract-secret',
        'cloudinary_prefix' => 'contract/public',
        'visibility' => 'public',
        'throw' => true,
    ]]);
});

it('resolves the registered cloud disk through Laravel and generates HTTPS URLs', function () {
    $disk = Storage::disk('cloud_contract');

    expect($disk)->toBeInstanceOf(CloudinaryFilesystem::class)
        ->and($disk->url('products/1/photo.webp'))->toStartWith('https://res.cloudinary.com/contract-cloud/raw/upload/v1/contract/public/products/1/photo.webp');
});

it('keeps the configured private cloud disk from generating public URLs', function () {
    config([
        'filesystems.disks.cloud_contract.visibility' => 'private',
        'filesystems.disks.cloud_contract.cloudinary_prefix' => 'contract/private',
    ]);

    Storage::disk('cloud_contract')->url('rmas/1/document.pdf');
})->throws(UnableToGeneratePublicUrl::class);

it('rejects missing cloud credentials rather than falling back to local uploads', function () {
    config(['filesystems.disks.cloud_contract.api_secret' => '']);

    Storage::disk('cloud_contract');
})->throws(InvalidArgumentException::class, 'Missing Cloudinary setting: api_secret');

it('uses the Brevo HTTPS transport for transactional mail', function () {
    config(['services.brevo.key' => 'contract-email-key']);

    expect(Mail::mailer('brevo')->getSymfonyTransport())->toBeInstanceOf(BrevoApiTransport::class);
});
