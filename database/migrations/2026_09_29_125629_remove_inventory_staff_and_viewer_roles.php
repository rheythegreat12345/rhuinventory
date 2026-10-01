<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $rhuStaffRoleId = DB::table('roles')->where('slug', 'rhu-staff')->value('id');

        if ($rhuStaffRoleId === null) {
            $rhuStaffRoleId = DB::table('roles')->insertGetId([
                'name' => 'RHU Staff',
                'slug' => 'rhu-staff',
                'description' => 'Read access to medicine and transaction records.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $removedRoleIds = DB::table('roles')
            ->whereIn('slug', ['inventory-staff', 'viewer'])
            ->pluck('id');

        if ($removedRoleIds->isEmpty()) {
            return;
        }

        DB::table('users')->whereIn('role_id', $removedRoleIds)->update([
            'role_id' => $rhuStaffRoleId,
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->whereIn('role_id', $removedRoleIds)->delete();
        DB::table('roles')->whereIn('id', $removedRoleIds)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            ['Inventory Staff', 'inventory-staff', 'Stock operations and inventory records.'],
            ['Viewer', 'viewer', 'Read-only inventory overview.'],
        ] as [$name, $slug, $description]) {
            DB::table('roles')->updateOrInsert(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
};
