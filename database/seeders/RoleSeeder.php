<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['Administrator', 'administrator', 'Full system access and configuration.'],
            ['Pharmacist', 'pharmacist', 'Medicine, dispensing, suppliers, and reports.'],
            ['Inventory Staff', 'inventory-staff', 'Stock operations and inventory records.'],
            ['RHU Staff', 'rhu-staff', 'Read access to medicine and transaction records.'],
            ['Viewer', 'viewer', 'Read-only inventory overview.'],
        ] as [$name, $slug, $description]) {
            Role::query()->updateOrCreate(['slug' => $slug], compact('name', 'description') + ['is_active' => true]);
        }
    }
}
