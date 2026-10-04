<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Payment attempts: unguessable public id for URLs, owner, expiry, the
 * verified gateway response, and one order per payment (report 9.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classy_payment_attempts', function (Blueprint $table) {
            $table->uuid('public_id')->nullable()->unique()->after('id');
            $table->unsignedBigInteger('customer_id')->nullable()->index()->after('cart_id');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('gateway_payload')->nullable();
        });

        DB::table('classy_payment_attempts')->whereNull('public_id')->orderBy('id')->each(
            fn ($row) => DB::table('classy_payment_attempts')
                ->where('id', $row->id)
                ->update(['public_id' => (string) Str::uuid()])
        );

        // One order per payment. Skip (and say so) if history already
        // contains a duplicate, rather than failing the deploy.
        $duplicates = DB::table('classy_payment_attempts')
            ->select('order_id')
            ->whereNotNull('order_id')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            Schema::table('classy_payment_attempts', function (Blueprint $table) {
                $table->unique('order_id', 'classy_payment_attempts_order_id_unique');
            });
        } else {
            Log::warning('classy_payment_attempts: duplicate order_id rows found; unique index skipped.');
        }
    }

    public function down(): void
    {
        Schema::table('classy_payment_attempts', function (Blueprint $table) {
            try {
                $table->dropUnique('classy_payment_attempts_order_id_unique');
            } catch (Throwable $e) {
            }

            $table->dropColumn(['public_id', 'customer_id', 'verified_at', 'expires_at', 'gateway_payload']);
        });
    }
};
