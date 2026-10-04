<?php

namespace Database\Seeders;

use ClassyFashion\Support\AccountSecurity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Webkul\Customer\Models\Customer;
use Webkul\User\Models\Admin;
use Webkul\User\Models\Role;

/**
 * Classy Fashion Hub project seed data (Gap #6 + test accounts).
 *
 * Creates the Worker role with limited permissions plus worker and
 * customer test accounts. Idempotent: safe to run multiple times.
 *
 * Locally and in tests the accounts use the credentials documented in
 * README.md. On production (APP_ENV=production) no published password is
 * ever used: demo accounts are created only when SEED_DEMO=true, with
 * SEED_WORKER_PASSWORD / SEED_CUSTOMER_PASSWORD or a random password that is
 * printed once, and the installer admin is rotated the same way
 * (SEED_ADMIN_PASSWORD).
 */
class ClassyFashionSeeder extends Seeder
{
    /**
     * Permissions granted to the Worker role.
     *
     * Workers manage catalogue, stock and orders. They cannot manage
     * users/roles, configuration, marketing content or delete records, and
     * they do not see profit figures (reporting.profit is owner-only).
     */
    public const WORKER_PERMISSIONS = [
        'dashboard',

        'catalog',
        'catalog.products',
        'catalog.products.create',
        'catalog.products.edit',
        'catalog.categories',

        'sales',
        'sales.orders',
        'sales.orders.view',
        'sales.orders.create',
        'sales.orders.cancel',
        'sales.orders.status',
        'sales.invoices',
        'sales.invoices.view',
        'sales.invoices.create',
        'sales.shipments',
        'sales.shipments.view',
        'sales.shipments.create',

        'customers',
        'customers.customers',

        'reporting',
        'reporting.sales',

        'sales.payments',
    ];

    public function run(): void
    {
        $validKeys = array_column(config('acl'), 'key');

        $permissions = array_values(array_intersect(self::WORKER_PERMISSIONS, $validKeys));

        $skipped = array_values(array_diff(self::WORKER_PERMISSIONS, $validKeys));

        if (! empty($skipped)) {
            $this->command?->warn('Worker role: unknown ACL keys skipped: '.implode(', ', $skipped));
        }

        $role = Role::updateOrCreate(
            ['name' => 'Worker'],
            [
                'description'   => 'Shop worker: catalogue, stock and order processing. No user management, configuration or deletes.',
                'permission_type' => 'custom',
                'permissions'     => $permissions,
            ]
        );

        if (AccountSecurity::demoAccountsAllowed()) {
            $this->upsert(Admin::class, 'worker', [
                'name'    => 'Shop Worker',
                'role_id' => $role->id,
                'status'  => 1,
            ]);

            $this->upsert(Customer::class, 'customer', [
                'first_name'        => 'Test',
                'last_name'         => 'Customer',
                'phone'             => '+256700000001',
                'customer_group_id' => 2,
                'channel_id'        => 1,
                'status'            => 1,
                'is_verified'       => 1,
            ]);
        } else {
            $this->command?->info('Production: demo worker/customer accounts skipped (set SEED_DEMO=true to create them).');
        }

        foreach (AccountSecurity::secure() as [$who, $email, $password]) {
            $this->command?->warn(
                $password === null
                    ? "Default {$who} password for {$email} replaced with the SEED_*_PASSWORD value."
                    : "NEW {$who} password for {$email} (shown once, change it after signing in): {$password}"
            );
        }

        $this->command?->info('Classy Fashion Hub seed data ready.');
    }

    /**
     * Create the account, or refresh it. Outside production the documented
     * password is (re)applied so tests and demos are deterministic; on
     * production an existing account keeps whatever password it has.
     */
    protected function upsert(string $model, string $who, array $attributes): void
    {
        $email = AccountSecurity::KNOWN[$who]['email'];

        $existing = $model::where('email', $email)->first();

        if ($existing && AccountSecurity::production()) {
            $existing->forceFill(Arr::except($attributes, ['password']))->save();

            return;
        }

        $generated = null;

        $password = AccountSecurity::passwordFor($who, $generated);

        $model::updateOrCreate(['email' => $email], $attributes + ['password' => Hash::make($password)]);

        if ($generated) {
            $this->command?->warn("NEW {$who} password for {$email} (shown once, change it after signing in): {$generated}");
        }
    }
}
