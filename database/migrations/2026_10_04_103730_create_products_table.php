<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('client_code', 50);
            $table->string('sku', 50);
            $table->string('uom', 20);
            $table->string('category', 30)->nullable();

            $table->decimal('unit_per_pack', 12, 4)->nullable();
            $table->decimal('weight_kg', 12, 4)->nullable();

            $table->timestamps();

            $table->unique(
                ['client_code', 'sku', 'uom'],
                'products_client_sku_uom_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};