<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

test('the audit log displays exact field changes without technical or sensitive values', function () {
    $administratorRole = Role::factory()->create(['slug' => 'administrator', 'is_active' => true]);
    $administrator = User::factory()->create(['role_id' => $administratorRole->id]);
    AuditLog::factory()->create([
        'user_id' => $administrator->id,
        'old_values' => [
            'generic_name' => 'Amlodipine',
            'strength' => '5 mg',
            'updated_at' => '2026-09-29T10:00:00.000000Z',
            'password' => 'old-password',
        ],
        'new_values' => [
            'generic_name' => 'Amlodipine',
            'strength' => '15 mg',
            'updated_at' => '2026-09-29T10:10:00.000000Z',
            'password' => 'new-password',
        ],
    ]);

    $this->actingAs($administrator)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertSee('View 1 change')
        ->assertSee('Strength')
        ->assertSee('5 mg')
        ->assertSee('15 mg')
        ->assertDontSee('updated at')
        ->assertDontSee('old-password')
        ->assertDontSee('new-password')
        ->assertDontSee('Before:');
});
