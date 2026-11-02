<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retain campaign fields present in 2.4 databases but removed from 2.5's original migration.
     */
    public function up(): void
    {
        foreach (['type', 'mail_to'] as $column) {
            if (! Schema::hasColumn('marketing_campaigns', $column)) {
                Schema::table('marketing_campaigns', function (Blueprint $table) use ($column) {
                    $table->string($column)->nullable();
                });
            }
        }
    }

    /**
     * Retain legacy business values during a rollback rather than dropping their columns.
     */
    public function down(): void {}
};
