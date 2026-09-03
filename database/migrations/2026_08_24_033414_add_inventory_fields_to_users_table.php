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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
            $table->string('status', 20)->default('active')->after('password')->index();
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->json('preferences')->nullable()->after('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'phone', 'job_title', 'status', 'last_login_at', 'preferences']);
        });
    }
};
