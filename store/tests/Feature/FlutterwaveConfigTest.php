<?php

use ClassyFashion\Support\Flutterwave;
use Illuminate\Support\Facades\Http;

it('only treats keys as ready when they match the configured mode', function () {
    config([
        'classy.flutterwave.public_key' => 'FLWPUBK_TEST-abc',
        'classy.flutterwave.secret_key' => 'FLWSECK_TEST-abc',
        'classy.flutterwave.sandbox'    => true,
    ]);

    expect(Flutterwave::configured())->toBeTrue();

    // A live key must never be used while the store is in sandbox mode.
    config(['classy.flutterwave.secret_key' => 'FLWSECK-live-abc']);
    expect(Flutterwave::configured())->toBeFalse();

    // ...and test keys must never be used in live mode.
    config(['classy.flutterwave.sandbox' => false, 'classy.flutterwave.secret_key' => 'FLWSECK_TEST-abc']);
    expect(Flutterwave::configured())->toBeFalse();

    config(['classy.flutterwave.secret_key' => 'FLWSECK-live-abc']);
    expect(Flutterwave::configured())->toBeTrue();

    config(['classy.flutterwave.secret_key' => '']);
    expect(Flutterwave::configured())->toBeFalse();
});

it('does not call the gateway when it is not configured', function () {
    Http::fake();

    config(['classy.flutterwave.secret_key' => '', 'classy.flutterwave.public_key' => '']);

    $result = Flutterwave::chargeUgandaMobileMoney(['tx_ref' => 'CFH-X', 'amount' => '1000']);

    expect($result['ok'])->toBeFalse();

    Http::assertNothingSent();
});

it('never calls env() outside config files (config:cache would return null)', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('packages/ClassyFashion/src'))
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Config'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        if (preg_match('/(?<![\w>:])env\s*\(/', file_get_contents($file->getPathname()))) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
});
