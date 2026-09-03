<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            MedicineCategorySeeder::class,
            SupplierSeeder::class,
            StorageLocationSeeder::class,
            MedicineSeeder::class,
            MedicineBatchSeeder::class,
            SettingSeeder::class,
        ]);

        $users = [
            ['System Administrator', 'admin@rhu.test', 'administrator', 'Municipal Health Administrator'],
            ['Dr. Elena Ramos', 'pharmacist@rhu.test', 'pharmacist', 'RHU Pharmacist'],
            ['Marco Villanueva', 'inventory@rhu.test', 'inventory-staff', 'Inventory Custodian'],
            ['Nurse Ana Flores', 'staff@rhu.test', 'rhu-staff', 'Public Health Nurse'],
            ['RHU Observer', 'viewer@rhu.test', 'viewer', 'Municipal Viewer'],
        ];

        foreach ($users as [$name, $email, $role, $jobTitle]) {
            User::query()->updateOrCreate(['email' => $email], [
                'role_id' => Role::query()->where('slug', $role)->value('id'),
                'name' => $name,
                'job_title' => $jobTitle,
                'phone' => '09'.fake()->numerify('#########'),
                'password' => 'password',
                'status' => 'active',
                'email_verified_at' => now(),
                'preferences' => ['theme' => 'system'],
            ]);
        }

        $this->call(DemoAccountSeeder::class);

        $this->call([
            InventoryTransactionSeeder::class,
            StockAdjustmentSeeder::class,
            InventoryNotificationSeeder::class,
            AuditLogSeeder::class,
        ]);
    }
}
