<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments confirmed by a person (cash on delivery, cash or transfer at the
 * shop), so 'Paid' is never an unexplained status (report 9.5/9.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classy_manual_payments', function (Blueprint $table) {
            $table->id();
            // int unsigned to match core orders/admins id columns (MySQL
            // rejects FKs across int/bigint).
            $table->unsignedInteger('order_id')->index();
            $table->string('method', 60);
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('received_by')->nullable();
            $table->timestamp('received_at');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classy_manual_payments');
    }
};
