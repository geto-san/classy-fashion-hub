<?php

namespace ClassyFashion\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Webkul\Customer\Models\Customer;
use Webkul\User\Models\Admin;

/**
 * Keeps the published demo passwords off production (report 10.2).
 *
 * The installer admin (admin123) and the project's worker/customer test
 * accounts are documented in public. Locally and in tests they keep those
 * passwords; on production they are replaced by a password you choose
 * (SEED_*_PASSWORD) or by a random one that is printed once.
 */
class AccountSecurity
{
    /**
     * Published credentials: who => [email, password, model].
     */
    public const KNOWN = [
        'admin'    => ['email' => 'admin@example.com',     'password' => 'admin123',    'model' => Admin::class],
        'worker'   => ['email' => 'worker@classy.local',   'password' => 'worker123',   'model' => Admin::class],
        'customer' => ['email' => 'customer@classy.local', 'password' => 'customer123', 'model' => Customer::class],
    ];

    public static function production(): bool
    {
        return app()->environment('production');
    }

    /**
     * Worker and customer demo accounts exist locally, in tests, and on
     * production only when SEED_DEMO=true.
     */
    public static function demoAccountsAllowed(): bool
    {
        return ! self::production() || (bool) config('classy.seed.demo');
    }

    /**
     * Password for a freshly seeded account: the configured one, else the
     * documented local default, else (production) a random one that the
     * caller must show once via $generated.
     */
    public static function passwordFor(string $who, ?string &$generated = null): string
    {
        $configured = config("classy.seed.{$who}_password");

        if (filled($configured)) {
            return (string) $configured;
        }

        if (! self::production()) {
            return self::KNOWN[$who]['password'];
        }

        return $generated = Str::random(24);
    }

    /**
     * Replace every account that still accepts its published password.
     * Does nothing outside production unless $force is set. Idempotent.
     *
     * @return array<int, array{0: string, 1: string, 2: ?string}>
     *         [who, email, generated password or null when SEED_* supplied it]
     */
    public static function secure(bool $force = false): array
    {
        if (! self::production() && ! $force) {
            return [];
        }

        $rows = [];

        foreach (self::KNOWN as $who => $definition) {
            /** @var Model|null $account */
            $account = $definition['model']::where('email', $definition['email'])->first();

            if (! $account || ! Hash::check($definition['password'], (string) $account->password)) {
                continue;
            }

            $configured = config("classy.seed.{$who}_password");

            $password = filled($configured) ? (string) $configured : Str::random(24);

            $account->forceFill(['password' => Hash::make($password)])->save();

            $rows[] = [$who, $definition['email'], filled($configured) ? null : $password];
        }

        self::renameAdmin();

        return $rows;
    }

    /**
     * Lost-password recovery for hosts without a shell (Render free tier).
     * Sets the admin's password to SEED_ADMIN_PASSWORD, whatever it was.
     * Returns the admin's e-mail, or null when nothing was done.
     */
    public static function resetAdmin(): ?string
    {
        $password = config('classy.seed.admin_password');

        if (blank($password)) {
            return null;
        }

        $admin = Admin::where('email', config('classy.seed.admin_email') ?: self::KNOWN['admin']['email'])->first()
            ?? Admin::where('email', self::KNOWN['admin']['email'])->first();

        if (! $admin) {
            return null;
        }

        $admin->forceFill(['password' => Hash::make((string) $password)])->save();

        return $admin->email;
    }

    /**
     * Optional: move the installer admin off admin@example.com.
     */
    protected static function renameAdmin(): void
    {
        $email = config('classy.seed.admin_email');

        if (blank($email) || ! self::production()) {
            return;
        }

        $admin = Admin::where('email', self::KNOWN['admin']['email'])->first();

        if ($admin && ! Admin::where('email', $email)->exists()) {
            $admin->forceFill(['email' => $email])->save();
        }
    }
}
