<?php

use ClassyFashion\Support\AccountSecurity;
use Database\Seeders\ClassyFashionSeeder;
use Illuminate\Support\Facades\Hash;
use Webkul\Customer\Models\Customer;
use Webkul\User\Models\Admin;

beforeEach(function () {
    $this->seed(ClassyFashionSeeder::class);
});

it('leaves the documented test passwords alone outside production', function () {
    expect(AccountSecurity::secure())->toBe([])
        ->and(Hash::check('worker123', Admin::where('email', 'worker@classy.local')->first()->password))->toBeTrue();
});

it('replaces every published password on production', function () {
    app()->detectEnvironment(fn () => 'production');

    config(['classy.seed.worker_password' => 'Chosen-By-Owner-1']);

    $rows = collect(AccountSecurity::secure())->keyBy(0);

    $worker = Admin::where('email', 'worker@classy.local')->first();
    $admin = Admin::where('email', 'admin@example.com')->first();
    $customer = Customer::where('email', 'customer@classy.local')->first();

    expect(Hash::check('worker123', $worker->password))->toBeFalse()
        ->and(Hash::check('Chosen-By-Owner-1', $worker->password))->toBeTrue()
        ->and($rows['worker'][2])->toBeNull()
        ->and(Hash::check('admin123', $admin->password))->toBeFalse()
        ->and(strlen($rows['admin'][2]))->toBe(24)
        ->and(Hash::check($rows['admin'][2], $admin->password))->toBeTrue()
        ->and(Hash::check('customer123', $customer->password))->toBeFalse();

    // Idempotent: a second boot finds nothing left to replace.
    expect(AccountSecurity::secure())->toBe([]);
});

it('never hands out a documented password on production', function () {
    app()->detectEnvironment(fn () => 'production');

    $generated = null;

    $password = AccountSecurity::passwordFor('worker', $generated);

    expect($password)->not->toBe('worker123')
        ->and($generated)->toBe($password)
        ->and(AccountSecurity::demoAccountsAllowed())->toBeFalse();

    config(['classy.seed.demo' => true]);

    expect(AccountSecurity::demoAccountsAllowed())->toBeTrue();
});

it('lets the owner recover a lost admin password from the environment', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(AccountSecurity::resetAdmin())->toBeNull(); // nothing configured: does nothing

    config(['classy.seed.admin_password' => 'Recovered-Pass-9']);

    $email = AccountSecurity::resetAdmin();

    $admin = Admin::where('email', $email)->first();

    expect($email)->toBe('admin@example.com')
        ->and(Hash::check('Recovered-Pass-9', $admin->password))->toBeTrue();
});
