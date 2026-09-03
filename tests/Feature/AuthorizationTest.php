<?php

use App\Models\Permission;
use App\Models\Role;

test('a viewer cannot open administrative pages', function () {
    $viewer = userWithPermissions(['medicines.view', 'transactions.view']);

    $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('settings.edit'))->assertForbidden();
    $this->actingAs($viewer)->get(route('stock.adjustment'))->assertForbidden();
});

test('permissions grant access to matching pages', function () {
    $staff = userWithPermissions(['stock.receive', 'stock.release', 'stock.adjust']);

    $this->actingAs($staff)->get(route('stock.in'))->assertOk();
    $this->actingAs($staff)->get(route('stock.out'))->assertOk();
    $this->actingAs($staff)->get(route('stock.adjustment'))->assertOk();
});

test('guests are redirected to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('medicines.index'))->assertRedirect(route('login'));
});

test('an administrator can update role permissions', function () {
    $administrator = userWithPermissions(['users.manage']);
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['slug' => 'reports.export']);

    $this->actingAs($administrator)->put(route('roles.update', $role), [
        'is_active' => true,
        'permission_ids' => [$permission->id],
    ])->assertRedirect();

    expect($role->fresh()->permissions->contains($permission))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'permissions_updated', 'auditable_id' => $role->id]);
});
