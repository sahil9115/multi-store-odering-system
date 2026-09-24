<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `orders.placed_at` was declared as the first TIMESTAMP column in the table (before
 * created_at/updated_at). On MySQL/MariaDB servers running with
 * `explicit_defaults_for_timestamp = OFF` (common default, e.g. stock XAMPP/MariaDB), the
 * first such column silently gets `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` —
 * meaning any update to an order (including a return's recalculation) was silently
 * overwriting the order's original placement time. Switching to DATETIME sidesteps this
 * MySQL-specific auto-initialization behavior entirely (only TIMESTAMP columns are subject
 * to it), independent of the server's explicit_defaults_for_timestamp setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE orders MODIFY placed_at DATETIME NOT NULL');

        // Repair any rows already corrupted by the auto-update behavior: created_at was set
        // by Eloquent at the exact same moment OrderPlacer set placed_at, so it's the only
        // reliable record of the true original value.
        DB::statement('UPDATE orders SET placed_at = created_at WHERE placed_at <> created_at');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders MODIFY placed_at TIMESTAMP NOT NULL');
    }
};
