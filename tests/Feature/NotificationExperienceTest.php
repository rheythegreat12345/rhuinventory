<?php

use App\Models\InventoryNotification;
use App\Models\InventoryNotificationRead;
use App\Models\MedicineBatch;
use App\Models\User;
use App\Services\AlertService;

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

test('the notification dropdown shows only unread notifications while the center retains history', function () {
    $user = userWithPermissions();
    $unreadNotification = InventoryNotification::factory()->create([
        'user_id' => $user->id,
        'title' => 'Unread dropdown alert',
        'read_at' => null,
    ]);
    $readNotification = InventoryNotification::factory()->create([
        'user_id' => $user->id,
        'title' => 'Read history alert',
        'read_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertViewHas('layoutNotifications', fn ($notifications) => $notifications->contains($unreadNotification) && ! $notifications->contains($readNotification))
        ->assertViewHas('unreadNotificationCount', 1);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertSee('Unread dropdown alert')
        ->assertSee('Read history alert');
});

test('deleting a notification removes it only from the current users history', function () {
    $user = userWithPermissions();
    $otherUser = User::factory()->create();
    $notification = InventoryNotification::factory()->create([
        'user_id' => null,
        'title' => 'Shared stock review',
        'read_at' => null,
    ]);

    $this->actingAs($user)
        ->from(route('notifications.index'))
        ->delete(route('notifications.destroy', $notification))
        ->assertRedirect(route('notifications.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('inventory_notification_reads', [
        'inventory_notification_id' => $notification->id,
        'user_id' => $user->id,
    ]);
    expect(InventoryNotificationRead::query()->where('inventory_notification_id', $notification->id)->where('user_id', $user->id)->firstOrFail()->deleted_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertViewHas('layoutNotifications', fn ($notifications) => ! $notifications->contains($notification));

    $this->actingAs($otherUser)
        ->get(route('notifications.index'))
        ->assertSee('Shared stock review')
        ->assertSee('Delete notification');
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

    expect($sharedNotification->fresh()->read_at)->toBeNull()
        ->and($personalNotification->fresh()->read_at)->not->toBeNull()
        ->and($otherNotification->fresh()->read_at)->toBeNull();
    $this->assertDatabaseHas('inventory_notification_reads', [
        'inventory_notification_id' => $sharedNotification->id,
        'user_id' => $user->id,
    ]);
});

test('reading a shared notification does not mark it read for another user', function () {
    $user = userWithPermissions();
    $otherUser = User::factory()->create();
    $sharedNotification = InventoryNotification::factory()->create(['user_id' => null, 'read_at' => null]);

    $this->actingAs($user)
        ->post(route('notifications.read', $sharedNotification))
        ->assertRedirect();

    expect($sharedNotification->fresh()->read_at)->toBeNull()
        ->and(InventoryNotificationRead::query()->where('inventory_notification_id', $sharedNotification->id)->where('user_id', $user->id)->exists())->toBeTrue();

    $this->actingAs($otherUser)
        ->get(route('notifications.index', ['status' => 'unread']))
        ->assertOk()
        ->assertSee($sharedNotification->title);
});

test('shared expiration alerts remain read for the reviewer and resolve when the batch is no longer active', function () {
    $user = userWithPermissions();
    $batch = MedicineBatch::factory()->create([
        'expiration_date' => today()->addDays(7),
        'quantity' => 10,
    ]);
    $alertService = app(AlertService::class);

    $alertService->syncExpirations();
    $notification = InventoryNotification::query()->where('type', 'expiring')->firstOrFail();
    $notification->markReadBy($user);

    $alertService->syncExpirations();

    expect(InventoryNotification::query()->where('type', 'expiring')->count())->toBe(1)
        ->and($notification->fresh()->isReadBy($user))->toBeTrue();

    $batch->update(['quantity' => 0]);
    $alertService->syncExpirations();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('staff cannot see or act on account approval notifications', function () {
    $staff = userWithPermissions();
    $approvalNotification = InventoryNotification::factory()->create([
        'user_id' => $staff->id,
        'type' => 'account_approval_requested',
        'title' => 'New account approval request',
        'read_at' => null,
    ]);
    $inventoryNotification = InventoryNotification::factory()->create([
        'user_id' => $staff->id,
        'title' => 'Low stock review',
        'read_at' => null,
    ]);

    $this->actingAs($staff)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertViewHas('totalCount', 1)
        ->assertViewHas('unreadCount', 1)
        ->assertSee('Low stock review')
        ->assertDontSee('New account approval request');

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertViewHas('layoutNotifications', fn ($notifications) => $notifications->contains($inventoryNotification) && ! $notifications->contains($approvalNotification))
        ->assertViewHas('unreadNotificationCount', 1);

    $this->actingAs($staff)
        ->post(route('notifications.read', $approvalNotification))
        ->assertForbidden();

    $this->actingAs($staff)
        ->from(route('notifications.index'))
        ->post(route('notifications.read-all'))
        ->assertRedirect(route('notifications.index'));

    expect($approvalNotification->fresh()->read_at)->toBeNull()
        ->and($inventoryNotification->fresh()->read_at)->not->toBeNull();
});
