<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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
 * Test credentials are documented in README.md (project root).
 */
class ClassyFashionSeeder extends Seeder
{
    /**
     * Permissions granted to the Worker role.
     *
     * Workers manage catalogue, stock and orders. They cannot manage
     * users/roles, configuration, marketing content or delete records.
     */
    public const WORKER_PERMISSIONS = [
        'dashboard',

        'catalog',
        'catalog.products',
        'catalog.products.create',
        'catalog.products.edit',

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
        'reporting.profit',
    ];

    public function run(): void
    {
        $validKeys = array_column(config('acl'), 'key');

        $permissions = array_values(array_intersect(self::WORKER_PERMISSIONS, $validKeys));

        $skipped = array_values(array_diff(self::WORKER_PERMISSIONS, $validKeys));

        if (! empty($skipped)) {
            $this->command->warn('Worker role: unknown ACL keys skipped: '.implode(', ', $skipped));
        }

        $role = Role::updateOrCreate(
            ['name' => 'Worker'],
            [
                'description'   => 'Shop worker: catalogue, stock and order processing. No user management, configuration or deletes.',
                'permission_type' => 'custom',
                'permissions'     => $permissions,
            ]
        );

        Admin::updateOrCreate(
            ['email' => 'worker@classy.local'],
            [
                'name'     => 'Shop Worker',
                'password' => Hash::make('worker123'),
                'role_id'  => $role->id,
                'status'   => 1,
            ]
        );

        Customer::updateOrCreate(
            ['email' => 'customer@classy.local'],
            [
                'first_name'        => 'Test',
                'last_name'         => 'Customer',
                'phone'             => '+256700000001',
                'password'          => Hash::make('customer123'),
                'customer_group_id' => 2,
                'channel_id'        => 1,
                'status'            => 1,
                'is_verified'       => 1,
            ]
        );

        $this->command->info('Classy Fashion Hub seed data ready (Worker role, worker + customer accounts).');
    }
}
