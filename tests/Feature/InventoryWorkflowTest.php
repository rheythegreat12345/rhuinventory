<?php

use App\Models\InventoryTransaction;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use App\Models\StockAdjustment;
use App\Models\StorageLocation;
use App\Models\Supplier;

function inventoryFixtures(): array
{
    $category = MedicineCategory::factory()->create();
    $supplier = Supplier::factory()->create();
    $location = StorageLocation::factory()->create();
    $medicine = Medicine::factory()->create([
        'medicine_category_id' => $category->id,
        'unit' => 'tablets',
        'minimum_stock_level' => 10,
        'maximum_stock_level' => 100,
    ]);

    return compact('medicine', 'supplier', 'location');
}

test('stock in creates a batch transaction and audit record', function () {
    $user = userWithPermissions(['stock.receive']);
    $fixtures = inventoryFixtures();

    $this->actingAs($user)->post(route('stock.in.store'), [
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'batch_number' => 'BATCH-IN-001',
        'quantity' => 40,
        'unit_cost' => 2.5,
        'expiration_date' => today()->addYear()->toDateString(),
        'received_at' => today()->toDateString(),
    ])->assertRedirect();

    expect(MedicineBatch::query()->where('batch_number', 'BATCH-IN-001')->value('quantity'))->toBe(40)
        ->and(InventoryTransaction::query()->where('type', 'stock_in')->count())->toBe(1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'stock_in', 'user_id' => $user->id]);
});

test('expired medicine cannot be received', function () {
    $user = userWithPermissions(['stock.receive']);
    $fixtures = inventoryFixtures();

    $this->actingAs($user)->post(route('stock.in.store'), [
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'batch_number' => 'EXPIRED-001',
        'quantity' => 10,
        'unit_cost' => 1,
        'expiration_date' => today()->subDay()->toDateString(),
        'received_at' => today()->toDateString(),
    ])->assertSessionHasErrors('expiration_date');

    expect(MedicineBatch::query()->count())->toBe(0);
});

test('stock out uses FEFO and never consumes expired batches', function () {
    $user = userWithPermissions(['stock.release']);
    $fixtures = inventoryFixtures();
    $medicine = $fixtures['medicine'];
    $early = MedicineBatch::factory()->create(['medicine_id' => $medicine->id, 'supplier_id' => $fixtures['supplier']->id, 'storage_location_id' => $fixtures['location']->id, 'batch_number' => 'EARLY', 'expiration_date' => today()->addMonth(), 'quantity' => 5]);
    $later = MedicineBatch::factory()->create(['medicine_id' => $medicine->id, 'supplier_id' => $fixtures['supplier']->id, 'storage_location_id' => $fixtures['location']->id, 'batch_number' => 'LATER', 'expiration_date' => today()->addYear(), 'quantity' => 10]);
    $expired = MedicineBatch::factory()->create(['medicine_id' => $medicine->id, 'supplier_id' => $fixtures['supplier']->id, 'storage_location_id' => $fixtures['location']->id, 'batch_number' => 'EXPIRED', 'expiration_date' => today()->subDay(), 'quantity' => 50]);

    $this->actingAs($user)->post(route('stock.out.store'), [
        'medicine_id' => $medicine->id,
        'quantity' => 8,
        'type' => 'dispensed',
        'purpose' => 'Patient dispensing',
        'transacted_at' => now()->format('Y-m-d H:i:s'),
    ])->assertRedirect();

    expect($early->fresh()->quantity)->toBe(0)
        ->and($later->fresh()->quantity)->toBe(7)
        ->and($expired->fresh()->quantity)->toBe(50)
        ->and(InventoryTransaction::query()->where('type', 'dispensed')->count())->toBe(2);
});

test('an adjustment cannot reduce batch stock below zero', function () {
    $user = userWithPermissions(['stock.adjust']);
    $fixtures = inventoryFixtures();
    $batch = MedicineBatch::factory()->create(['medicine_id' => $fixtures['medicine']->id, 'supplier_id' => $fixtures['supplier']->id, 'storage_location_id' => $fixtures['location']->id, 'quantity' => 4]);

    $this->actingAs($user)->post(route('stock.adjustment.store'), [
        'medicine_batch_id' => $batch->id,
        'quantity_difference' => -5,
        'reason' => 'damaged',
        'notes' => 'Damaged during inspection.',
    ])->assertSessionHasErrors('quantity_difference');

    expect($batch->fresh()->quantity)->toBe(4)
        ->and(StockAdjustment::query()->count())->toBe(0);
});

test('a valid adjustment records before and after stock', function () {
    $user = userWithPermissions(['stock.adjust']);
    $fixtures = inventoryFixtures();
    $batch = MedicineBatch::factory()->create(['medicine_id' => $fixtures['medicine']->id, 'supplier_id' => $fixtures['supplier']->id, 'storage_location_id' => $fixtures['location']->id, 'quantity' => 10]);

    $this->actingAs($user)->post(route('stock.adjustment.store'), [
        'medicine_batch_id' => $batch->id,
        'quantity_difference' => -2,
        'reason' => 'damaged',
        'notes' => 'Packaging damaged during inspection.',
    ])->assertRedirect();

    expect($batch->fresh()->quantity)->toBe(8)
        ->and(StockAdjustment::query()->where('quantity_difference', -2)->exists())->toBeTrue();
    $this->assertDatabaseHas('inventory_transactions', ['previous_stock' => 10, 'new_stock' => 8]);
});
