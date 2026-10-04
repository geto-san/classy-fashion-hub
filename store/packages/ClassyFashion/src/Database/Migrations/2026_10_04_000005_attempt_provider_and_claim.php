<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment attempts: which provider handled them, plus the customer's own
 * transaction claim for the manual Till flow (report 9.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classy_payment_attempts', function (Blueprint $table) {
            $table->string('provider', 20)->nullable()->after('network');
            $table->string('customer_claim', 100)->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('classy_payment_attempts', function (Blueprint $table) {
            $table->dropColumn(['provider', 'customer_claim']);
        });
    }
};
