<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

test('a viewer cannot open administrative pages', function () {
    $viewer = userWithPermissions(['medicines.view', 'transactions.view']);

    $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();
    $this->actingAs($viewer)->put(route('users.approve', User::factory()->create(['status' => 'inactive'])))->assertForbidden();
    $this->actingAs($viewer)->get(route('settings.edit'))->assertForbidden();
    $this->actingAs($viewer)->get(route('stock.adjustment'))->assertForbidden();
});

test('a non-administrator cannot access account controls even with administrative permissions', function () {
    $staff = userWithPermissions(['users.manage', 'audit.view', 'settings.manage']);

    $this->actingAs($staff)->get(route('admin.accounts.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('audit.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('settings.edit'))->assertForbidden();
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
    $administratorRole = Role::factory()->create(['slug' => 'administrator', 'is_active' => true]);
    $administrator = User::factory()->create(['role_id' => $administratorRole->id]);
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['slug' => 'reports.export']);

    $this->actingAs($administrator)->put(route('roles.update', $role), [
        'is_active' => true,
        'permission_ids' => [$permission->id],
    ])->assertRedirect();

    expect($role->fresh()->permissions->contains($permission))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'permissions_updated', 'auditable_id' => $role->id]);
});
