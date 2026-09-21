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
        Schema::table('inventory_notification_reads', function (Blueprint $table): void {
            $table->timestamp('deleted_at')->nullable()->after('read_at');
            $table->index(['user_id', 'deleted_at'], 'notification_read_user_deleted_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_notification_reads', function (Blueprint $table): void {
            $table->dropIndex('notification_read_user_deleted_index');
            $table->dropColumn('deleted_at');
        });
    }
};
