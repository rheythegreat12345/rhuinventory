<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'demo@rhu.test'],
            [
                'role_id' => Role::query()->where('slug', 'administrator')->firstOrFail()->id,
                'name' => 'Demo Administrator',
                'job_title' => 'System Demonstration Account',
                'phone' => '0917 000 0000',
                'password' => 'demo1234',
                'status' => 'active',
                'email_verified_at' => now(),
                'preferences' => ['theme' => 'system'],
            ],
        );
    }
}
