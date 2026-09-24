<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained();
            $table->unsignedInteger('quantity_allocated');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->timestamps();

            $table->index('order_item_id');
            $table->index('store_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreign('order_item_allocation_id')
                ->references('id')->on('order_item_allocations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['order_item_allocation_id']);
        });

        Schema::dropIfExists('order_item_allocations');
    }
};
