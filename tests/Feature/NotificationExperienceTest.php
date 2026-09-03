<?php

use App\Models\InventoryNotification;
use App\Models\User;

test('notification center shows accurate user scoped counts and filters', function () {
    $user = userWithPermissions();
    $otherUser = User::factory()->create();

    InventoryNotification::factory()->create([
        'title' => 'Shared unread alert',
        'user_id' => null,
        'read_at' => null,
    ]);
    InventoryNotification::factory()->create([
        'title' => 'Personal read alert',
        'user_id' => $user->id,
        'read_at' => now(),
    ]);
    InventoryNotification::factory()->create([
        'title' => 'Another user alert',
        'user_id' => $otherUser->id,
        'read_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertViewHas('totalCount', 2)
        ->assertViewHas('unreadCount', 1)
        ->assertSee('Shared unread alert')
        ->assertSee('Personal read alert')
        ->assertDontSee('Another user alert')
        ->assertSee('Notifications, 1 unread', false);

    $this->actingAs($user)
        ->get(route('notifications.index', ['status' => 'unread']))
        ->assertOk()
        ->assertViewHas('notifications', fn ($notifications) => $notifications->total() === 1)
        ->assertSee('Shared unread alert');
});

test('opening a notification marks it as read without changing its content', function () {
    $user = userWithPermissions();
    $notification = InventoryNotification::factory()->create([
        'user_id' => $user->id,
        'title' => 'Low stock review',
        'message' => 'Review the current medicine stock.',
        'data' => ['url' => route('notifications.index')],
    ]);

    $this->actingAs($user)
        ->post(route('notifications.read', $notification))
        ->assertRedirect(route('notifications.index'));

    $notification->refresh();

    expect($notification->read_at)->not->toBeNull()
        ->and($notification->title)->toBe('Low stock review')
        ->and($notification->message)->toBe('Review the current medicine stock.');
});

test('mark all read only updates notifications visible to the current user', function () {
    $user = userWithPermissions();
    $otherUser = User::factory()->create();
    $sharedNotification = InventoryNotification::factory()->create(['user_id' => null]);
    $personalNotification = InventoryNotification::factory()->create(['user_id' => $user->id]);
    $otherNotification = InventoryNotification::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->from(route('notifications.index'))
        ->post(route('notifications.read-all'))
        ->assertRedirect(route('notifications.index'))
        ->assertSessionHas('success');

    expect($sharedNotification->fresh()->read_at)->not->toBeNull()
        ->and($personalNotification->fresh()->read_at)->not->toBeNull()
        ->and($otherNotification->fresh()->read_at)->toBeNull();
});
