<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->get()->each(function (User $user, int $index): void {
            AuditLog::query()->create([
                'user_id' => $user->id,
                'action' => 'login',
                'auditable_type' => $user->getMorphClass(),
                'auditable_id' => $user->id,
                'description' => 'Sample successful sign-in activity.',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeded demonstration record',
                'created_at' => now()->subDays($index)->setTime(8 + $index, 10),
                'updated_at' => now()->subDays($index)->setTime(8 + $index, 10),
            ]);
        });
    }
}
