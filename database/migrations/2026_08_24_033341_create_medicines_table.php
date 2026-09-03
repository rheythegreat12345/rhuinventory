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
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_category_id')->constrained()->restrictOnDelete();
            $table->string('medicine_code', 30)->unique();
            $table->string('barcode', 80)->nullable()->unique();
            $table->string('generic_name')->index();
            $table->string('brand_name')->nullable()->index();
            $table->string('dosage')->nullable();
            $table->string('strength')->nullable();
            $table->string('dosage_form', 80);
            $table->string('unit', 40);
            $table->unsignedInteger('minimum_stock_level')->default(10);
            $table->unsignedInteger('maximum_stock_level')->default(100);
            $table->unsignedInteger('reorder_level')->default(20);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('reference_price', 12, 2)->nullable();
            $table->string('storage_condition')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
