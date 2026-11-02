<?php

use ClassyFashion\Repositories\DeploymentRepository;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('marks an imported store installed without requiring an environment file', function () {
    $original = app()->storagePath();
    $temporary = sys_get_temp_dir().'/classy-ready-'.Str::uuid();
    mkdir($temporary);
    app()->useStoragePath($temporary);

    try {
        $this->artisan('classy:assert-deployment-ready')->assertSuccessful();
        expect(is_file($temporary.'/installed'))->toBeTrue();
    } finally {
        app()->useStoragePath($original);
        @unlink($temporary.'/installed');
        rmdir($temporary);
    }
});

it('does not create an installed marker when the database import is incomplete', function () {
    $repository = Mockery::mock(DeploymentRepository::class);
    $repository->shouldReceive('assertReady')->once()->andThrow(new RuntimeException('Import incomplete.'));
    app()->instance(DeploymentRepository::class, $repository);
    $original = app()->storagePath();
    $temporary = sys_get_temp_dir().'/classy-not-ready-'.Str::uuid();
    mkdir($temporary);
    app()->useStoragePath($temporary);
    $this->expectException(RuntimeException::class);

    try {
        $this->artisan('classy:assert-deployment-ready')->run();
    } finally {
        app()->useStoragePath($original);
        expect(is_file($temporary.'/installed'))->toBeFalse();
        rmdir($temporary);
    }
});

it('rejects an incomplete application schema', function () {
    Schema::shouldReceive('hasTable')->once()->with('admins')->andReturnFalse();

    expect(fn () => app(DeploymentRepository::class)->assertReady())
        ->toThrow(RuntimeException::class, 'Store schema is incomplete.');
});
