<?php

use ClassyFashion\Support\Profit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Webkul\Sales\Models\OrderItem;

/**
 * Cost per sale (report 9.4): remember what an item cost when it was sold,
 * so later cost edits do not rewrite past profit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 4)->nullable()->after('base_price');
        });

        // Best-effort backfill for existing sales (uses today's cost, the
        // only figure available). Never blocks a deploy.
        try {
            OrderItem::query()
                ->whereNull('parent_id')
                ->whereNull('cost_price')
                ->with('product')
                ->chunkById(200, function ($items) {
                    foreach ($items as $item) {
                        $item->cost_price = Profit::currentCost($item);
                        $item->saveQuietly();
                    }
                });
        } catch (Throwable $e) {
            Log::warning('cost_price backfill skipped: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
