<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile-money payment attempts (report 9.6).
 *
 * Pending, successful and failed payments stay distinguishable with their
 * gateway transaction reference, whether or not an order was created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classy_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('tx_ref')->unique();
            $table->string('gateway_tx_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('UGX');
            $table->string('network', 20)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classy_payment_attempts');
    }
};
