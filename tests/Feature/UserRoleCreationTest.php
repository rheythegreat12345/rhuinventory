<?php

use App\Models\Role;
use App\Models\User;

test('an administrator can create a role while creating an account', function () {
    $administratorRole = Role::factory()->create(['slug' => 'administrator', 'is_active' => true]);
    $administrator = User::factory()->create(['role_id' => $administratorRole->id]);

    $this->actingAs($administrator)->post(route('users.store'), [
        'name' => 'Lara Cruz',
        'email' => 'lara.cruz@example.test',
        'new_role_name' => 'Laboratory Officer',
        'new_role_description' => 'Manages laboratory inventory records.',
        'password' => 'Secure!Pass123',
        'password_confirmation' => 'Secure!Pass123',
        'status' => 'active',
    ])->assertRedirect();

    $role = Role::query()->where('slug', 'laboratory-officer')->firstOrFail();
    $this->assertDatabaseHas('users', ['email' => 'lara.cruz@example.test', 'role_id' => $role->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'role_created', 'auditable_id' => $role->id]);

    $this->actingAs($administrator)->get(route('users.create'))
        ->assertOk()
        ->assertSee('Or create a new role');
});
