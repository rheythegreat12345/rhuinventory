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
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 40)->unique();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('previous_stock');
            $table->unsignedInteger('new_stock');
            $table->string('recipient')->nullable();
            $table->string('purpose')->nullable();
            $table->timestamp('transacted_at')->index();
            $table->string('reference_number')->nullable()->index();
            $table->string('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
