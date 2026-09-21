<?php

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('the seeded application pages render for an administrator', function () {
    $this->seed(DatabaseSeeder::class);
    $administrator = User::query()->where('email', 'admin@rhu.test')->firstOrFail();
    $medicine = Medicine::query()->firstOrFail();

    $routes = [
        route('dashboard'),
        route('medicines.index'),
        route('medicines.show', $medicine),
        route('inventory.expirations'),
        route('transactions.index'),
        route('reports.index'),
        route('analytics'),
        route('notifications.index'),
        route('users.index'),
        route('roles.index'),
        route('settings.edit'),
        route('scanner'),
    ];

    foreach ($routes as $url) {
        $this->actingAs($administrator)->get($url)->assertOk();
    }

    $this->actingAs($administrator)
        ->get(route('inventory.expirations'))
        ->assertSee('>Manage<', false)
        ->assertSee('Restock');
});

test('the dashboard uses bounded grids for its right-side cards', function () {
    $this->seed(DatabaseSeeder::class);
    $administrator = User::query()->where('email', 'admin@rhu.test')->firstOrFail();

    $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('dashboard-overview-grid', false)
        ->assertSee('dashboard-workspace-grid', false)
        ->assertSee('dashboard-action-center', false)
        ->assertSee('dashboard-quick-actions', false)
        ->assertSee('dashboard-expiring-panel', false)
        ->assertSee('dashboard-stock-attention-item', false)
        ->assertSee('Workspace shortcuts')
        ->assertSee('&quot;label&quot;:&quot;Received&quot;', false)
        ->assertSee('&quot;label&quot;:&quot;Released&quot;', false)
        ->assertSee('&quot;label&quot;:&quot;Adjusted&quot;', false)
        ->assertSee(route('inventory.low-stock'), false)
        ->assertSee(route('inventory.expirations'), false);
});

test('reports refresh automatically when a filter changes', function () {
    $user = userWithPermissions(['reports.view']);

    $this->actingAs($user)
        ->get(route('reports.index', ['report' => 'expiring']))
        ->assertOk()
        ->assertSee('Expiring Medicines Report')
        ->assertSee('data-auto-submit', false)
        ->assertDontSee('>Generate<', false);
});

test('the dashboard treats expired stock as unavailable', function () {
    $user = userWithPermissions();
    $medicine = Medicine::factory()->create([
        'generic_name' => 'Expired Dashboard Medicine',
        'unit' => 'tablets',
        'minimum_stock_level' => 10,
    ]);
    MedicineBatch::factory()->create([
        'medicine_id' => $medicine->id,
        'supplier_id' => Supplier::factory(),
        'storage_location_id' => StorageLocation::factory(),
        'quantity' => 500,
        'expiration_date' => today()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Expired Dashboard Medicine')
        ->assertSee('0 tablets left');
});

test('the dashboard and expiration monitor exclude archived medicines', function () {
    $user = userWithPermissions(['medicines.view']);
    $medicine = Medicine::factory()->create(['generic_name' => 'Archived Dashboard Medicine']);
    MedicineBatch::factory()->create([
        'medicine_id' => $medicine->id,
        'supplier_id' => Supplier::factory(),
        'storage_location_id' => StorageLocation::factory(),
        'quantity' => 50,
        'expiration_date' => today()->addWeek(),
    ]);
    $medicine->update(['status' => 'archived']);
    $medicine->delete();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Archived Dashboard Medicine');

    $this->actingAs($user)
        ->get(route('inventory.expirations'))
        ->assertOk()
        ->assertDontSee('Archived Dashboard Medicine');
});
