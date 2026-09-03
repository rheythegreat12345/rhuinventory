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
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('storage_location_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 80);
            $table->string('lot_number', 80)->nullable();
            $table->date('manufacturing_date')->nullable();
            $table->date('expiration_date')->index();
            $table->unsignedInteger('quantity')->default(0)->index();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->date('received_at')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->unique(['medicine_id', 'batch_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicine_batches');
    }
};
