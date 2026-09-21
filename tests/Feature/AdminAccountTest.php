<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function administrator(): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'administrator'],
        [
            'name' => 'Administrator',
            'description' => 'Full system access.',
            'is_active' => true,
        ],
    );

    return User::factory()->create(['role_id' => $role->id]);
}

test('only administrators can access account security controls', function () {
    $staff = userWithPermissions(['users.manage']);

    $this->actingAs($staff)->get(route('admin.accounts.index'))->assertForbidden();

    $this->actingAs(administrator())->get(route('admin.accounts.index'))
        ->assertOk()
        ->assertSee('Admin account controls')
        ->assertSee('Pending access requests')
        ->assertSee('Staff password resets');
});

test('an administrator can reset a staff password and the reset is audited', function () {
    $administrator = administrator();
    $staffRole = Role::factory()->create(['slug' => 'rhu-staff', 'is_active' => true]);
    $staff = User::factory()->create(['role_id' => $staffRole->id]);

    $this->actingAs($administrator)->put(route('admin.accounts.password.reset', $staff), [
        'password' => 'NewSecure!Pass123',
        'password_confirmation' => 'NewSecure!Pass123',
    ])->assertRedirect()
        ->assertSessionHas('success', "Password reset for {$staff->name}.");

    expect(Hash::check('NewSecure!Pass123', $staff->fresh()->password))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin_password_reset',
        'auditable_id' => $staff->id,
    ]);
});

test('an administrator cannot reset another administrator password', function () {
    $administrator = administrator();
    $otherAdministrator = administrator();

    $this->actingAs($administrator)->put(route('admin.accounts.password.reset', $otherAdministrator), [
        'password' => 'NewSecure!Pass123',
        'password_confirmation' => 'NewSecure!Pass123',
    ])->assertForbidden();
});

test('an administrator can delete a staff account and retain an audit record', function () {
    $administrator = administrator();
    $staff = User::factory()->create();

    $this->actingAs($administrator)->delete(route('users.destroy', $staff))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success', 'User account deleted.');

    $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user_deleted',
        'auditable_id' => $staff->id,
    ]);
});
