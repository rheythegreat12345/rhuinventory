<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'medicines.view' => ['View medicines', 'Medicines'],
            'medicines.create' => ['Add and import medicines', 'Medicines'],
            'medicines.edit' => ['Edit and archive medicines', 'Medicines'],
            'stock.receive' => ['Receive stock', 'Stock'],
            'stock.release' => ['Release and dispense stock', 'Stock'],
            'stock.adjust' => ['Adjust stock', 'Stock'],
            'transactions.view' => ['View transactions', 'Transactions'],
            'categories.manage' => ['Manage categories', 'Catalog'],
            'suppliers.manage' => ['Manage suppliers', 'Catalog'],
            'reports.view' => ['View reports', 'Reports'],
            'reports.export' => ['Export reports', 'Reports'],
            'analytics.view' => ['View analytics', 'Reports'],
            'users.manage' => ['Manage users', 'Administration'],
            'audit.view' => ['View audit logs', 'Administration'],
            'settings.manage' => ['Manage settings', 'Administration'],
        ];

        foreach ($permissions as $slug => [$name, $group]) {
            Permission::query()->updateOrCreate(['slug' => $slug], compact('name', 'group'));
        }

        $grants = [
            'administrator' => array_keys($permissions),
            'pharmacist' => ['medicines.view', 'medicines.create', 'medicines.edit', 'stock.receive', 'stock.release', 'stock.adjust', 'transactions.view', 'categories.manage', 'suppliers.manage', 'reports.view', 'reports.export', 'analytics.view'],
            'inventory-staff' => ['medicines.view', 'medicines.create', 'medicines.edit', 'stock.receive', 'stock.release', 'stock.adjust', 'transactions.view', 'reports.view', 'reports.export'],
            'rhu-staff' => ['medicines.view', 'transactions.view', 'reports.view'],
            'viewer' => ['medicines.view', 'transactions.view', 'reports.view'],
        ];

        foreach ($grants as $roleSlug => $permissionSlugs) {
            Role::query()->where('slug', $roleSlug)->firstOrFail()->permissions()->sync(
                Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id'),
            );
        }
    }
}
