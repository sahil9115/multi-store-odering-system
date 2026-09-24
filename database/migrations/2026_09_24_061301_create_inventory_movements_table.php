<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_product_id')->constrained('store_product')->cascadeOnDelete();
            // order_item_allocations doesn't exist yet (created in Phase 6) — the FK constraint
            // for this column is added by 2026_09_24_061602_add_foreign_key_to_inventory_movements_table.
            $table->unsignedBigInteger('order_item_allocation_id')->nullable();
            $table->string('type');
            $table->integer('quantity_delta');
            $table->unsignedInteger('balance_after');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
