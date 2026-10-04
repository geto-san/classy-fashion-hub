<?php

namespace ClassyFashion\Console\Commands;

use ClassyFashion\Support\AccountSecurity;
use Illuminate\Console\Command;

/**
 * Replace the published default passwords on this database.
 *
 * Run on every production boot (deploy/entrypoint.sh), so databases that
 * were seeded before this existed are fixed too. Safe to repeat.
 */
class SecureAccounts extends Command
{
    protected $signature = 'classy:secure-accounts
        {--force : Rotate even outside production}
        {--reset-admin : Set the admin password to SEED_ADMIN_PASSWORD (lost-password recovery)}';

    protected $description = 'Replace the published default passwords (admin123, worker123, customer123)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if ($this->option('reset-admin')) {
            $email = AccountSecurity::resetAdmin();

            $email
                ? $this->warn("Admin password for {$email} reset to the SEED_ADMIN_PASSWORD value. Remove RESET_ADMIN_PASSWORD now.")
                : $this->error('Nothing reset: set SEED_ADMIN_PASSWORD and make sure the admin account exists.');
        }

        if (! AccountSecurity::production() && ! $force) {
            $this->line('Not production: documented test passwords left as they are (use --force to rotate).');

            return self::SUCCESS;
        }

        $rows = AccountSecurity::secure($force);

        if ($rows === []) {
            $this->info('No account uses a published default password.');

            return self::SUCCESS;
        }

        foreach ($rows as [$who, $email, $password]) {
            if ($password === null) {
                $this->info("Replaced default {$who} password for {$email} with the SEED_*_PASSWORD value.");

                continue;
            }

            $this->warn("NEW {$who} password for {$email} (shown once, change it after signing in): {$password}");
        }

        return self::SUCCESS;
    }
}
