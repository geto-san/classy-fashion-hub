<?php

namespace ClassyFashion\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DeploymentRepository
{
    /**
     * Refuse to serve an incomplete migration as a new installation.
     */
    public function assertReady(): void
    {
        foreach (['admins', 'channels', 'sessions', 'core_config'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Store schema is incomplete. Finish the database migration before deployment.');
            }
        }

        if (! DB::table('admins')->exists() || ! DB::table('channels')->exists()) {
            throw new RuntimeException('Store data is missing. Finish the data import before deployment.');
        }
    }
}
