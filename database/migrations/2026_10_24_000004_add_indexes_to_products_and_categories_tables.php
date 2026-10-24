<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['deleted_at', 'status'], 'products_deleted_at_status_idx');
            $table->index(['deleted_at', 'created_at'], 'products_deleted_at_created_at_idx');
            $table->index('price', 'products_price_idx');
            $table->index('stock', 'products_stock_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['deleted_at', 'is_active'], 'categories_deleted_at_is_active_idx');
            $table->index('name', 'categories_name_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_deleted_at_status_idx');
            $table->dropIndex('products_deleted_at_created_at_idx');
            $table->dropIndex('products_price_idx');
            $table->dropIndex('products_stock_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_deleted_at_is_active_idx');
            $table->dropIndex('categories_name_idx');
        });
    }
};
