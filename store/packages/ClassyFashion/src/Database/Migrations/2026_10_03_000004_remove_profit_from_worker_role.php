<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Workers do not see cost or profit (report 8, 10.2). Fixes databases that
 * were seeded while the Worker role still carried 'reporting.profit'.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('roles')->where('name', 'Worker')->first();

        if (! $role) {
            return;
        }

        $permissions = json_decode((string) $role->permissions, true) ?: [];

        DB::table('roles')->where('id', $role->id)->update([
            'permissions' => json_encode(array_values(array_diff($permissions, ['reporting.profit']))),
        ]);
    }

    public function down(): void {}
};
