<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('roles')->where('name', 'Worker')->first();

        if (! $role) {
            return;
        }

        $permissions = json_decode((string) $role->permissions, true) ?: [];
        $permissions = array_diff($permissions, ['catalog.products.create', 'catalog.products.edit']);
        $permissions[] = 'catalog.stock';

        DB::table('roles')->where('id', $role->id)->update([
            'permissions' => json_encode(array_values(array_unique($permissions))),
        ]);
    }

    public function down(): void {}
};
