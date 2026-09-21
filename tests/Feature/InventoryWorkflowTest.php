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
    ])->assertRedirect();

    $receivedBatch = MedicineBatch::query()->where('batch_number', 'BATCH-IN-001')->firstOrFail();

    expect($receivedBatch->quantity)->toBe(40)
        ->and($receivedBatch->received_at->toDateString())->toBe(today()->toDateString())
        ->and(InventoryTransaction::query()->where('type', 'stock_in')->count())->toBe(1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'stock_in', 'user_id' => $user->id]);
});

test('stock in form shows only essential receipt fields', function () {
    $user = userWithPermissions(['stock.receive']);
    inventoryFixtures();

    $this->actingAs($user)
        ->get(route('stock.in'))
        ->assertOk()
        ->assertSee('for="batch_number"', false)
        ->assertSee('Batch / lot number')
        ->assertDontSee('for="storage_location_id"', false)
        ->assertSee('for="quantity_unit"', false)
        ->assertSee('data-box-receipt-medicine', false)
        ->assertSee('id="box-receipt-reminder"', false)
        ->assertSee('for="expiration_date"', false)
        ->assertDontSee('for="lot_number"', false)
        ->assertDontSee('for="manufacturing_date"', false)
        ->assertDontSee('for="received_at"', false)
        ->assertDontSee('for="reference_number"', false)
        ->assertDontSee('for="remarks"', false);
});

test('stock in uses the default active storage location when none is submitted', function () {
    $user = userWithPermissions(['stock.receive']);
    $fixtures = inventoryFixtures();

    $this->actingAs($user)->post(route('stock.in.store'), [
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'batch_number' => 'AUTO-LOCATION-001',
        'quantity' => 40,
        'unit_cost' => 2.5,
        'expiration_date' => today()->addYear()->toDateString(),
    ])->assertRedirect();

    $this->assertDatabaseHas('medicine_batches', [
        'batch_number' => 'AUTO-LOCATION-001',
        'storage_location_id' => $fixtures['location']->id,
    ]);
});

test('stock in converts boxes to base inventory units', function () {
    $user = userWithPermissions(['stock.receive']);
    $fixtures = inventoryFixtures();
    $fixtures['medicine']->update(['box_size' => 100]);

    $this->actingAs($user)->post(route('stock.in.store'), [
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'batch_number' => 'BOX-IN-001',
        'quantity' => 3,
        'quantity_unit' => 'box',
        'unit_cost' => 2.5,
        'expiration_date' => today()->addYear()->toDateString(),
    ])->assertRedirect();

    $receivedBatch = MedicineBatch::query()->where('batch_number', 'BOX-IN-001')->firstOrFail();
    $transaction = InventoryTransaction::query()->where('medicine_batch_id', $receivedBatch->id)->firstOrFail();

    expect($receivedBatch->quantity)->toBe(300)
        ->and($transaction->quantity)->toBe(300)
        ->and($transaction->metadata)->toMatchArray([
            'entered_quantity' => 3,
            'entered_unit' => 'box',
            'units_per_box' => 100,
        ]);
});

test('stock in requires the configured units per box before converting boxes', function () {
    $user = userWithPermissions(['stock.receive']);
    $fixtures = inventoryFixtures();

    $this->actingAs($user)->post(route('stock.in.store'), [
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'batch_number' => 'BOX-IN-NO-CONFIG',
        'quantity' => 3,
        'quantity_unit' => 'box',
        'unit_cost' => 2.5,
        'expiration_date' => today()->addYear()->toDateString(),
    ])->assertSessionHasErrors('quantity_unit');

    $this->assertDatabaseMissing('medicine_batches', ['batch_number' => 'BOX-IN-NO-CONFIG']);
});

test('stock in does not merge a different lot number into an existing batch', function () {
    $user = userWithPermissions(['stock.receive']);
    $fixtures = inventoryFixtures();
    $receipt = [
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'batch_number' => 'BATCH-LOT-001',
        'quantity' => 10,
        'unit_cost' => 2.5,
        'expiration_date' => today()->addYear()->toDateString(),
        'received_at' => today()->toDateString(),
    ];

    $this->actingAs($user)->post(route('stock.in.store'), [...$receipt, 'lot_number' => 'LOT-A'])->assertRedirect();

    $this->actingAs($user)
        ->post(route('stock.in.store'), [...$receipt, 'lot_number' => 'LOT-B'])
        ->assertSessionHasErrors('lot_number');

    expect(MedicineBatch::query()->where('batch_number', 'BATCH-LOT-001')->value('quantity'))->toBe(10);
});

test('stock in does not change the supplier or storage location of an existing batch', function () {
    $user = userWithPermissions(['stock.receive']);
    $fixtures = inventoryFixtures();
    $otherSupplier = Supplier::factory()->create();
    $otherLocation = StorageLocation::factory()->create();
    $receipt = [
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'batch_number' => 'BATCH-SOURCE-001',
        'quantity' => 10,
        'unit_cost' => 2.5,
        'expiration_date' => today()->addYear()->toDateString(),
    ];

    $this->actingAs($user)->post(route('stock.in.store'), $receipt)->assertRedirect();

    $this->actingAs($user)
        ->post(route('stock.in.store'), [...$receipt, 'supplier_id' => $otherSupplier->id, 'storage_location_id' => $otherLocation->id])
        ->assertSessionHasErrors('batch_number');

    $batch = MedicineBatch::query()->where('batch_number', 'BATCH-SOURCE-001')->firstOrFail();
    expect($batch->supplier_id)->toBe($fixtures['supplier']->id)
        ->and($batch->storage_location_id)->toBe($fixtures['location']->id)
        ->and($batch->quantity)->toBe(10);
});

test('expired stock is not treated as usable stock or available for reorder decisions', function () {
    $fixtures = inventoryFixtures();
    MedicineBatch::factory()->create([
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'quantity' => 500,
        'expiration_date' => today()->subDay(),
    ]);

    $medicine = $fixtures['medicine']->fresh();

    expect($medicine->usableStockQuantity())->toBe(0)
        ->and($medicine->stockStatus())->toBe('out')
        ->and($medicine->recommendedReorderQuantity())->toBe(100);
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
        'recipient' => 'Ana Cruz',
    ])->assertRedirect();

    expect($early->fresh()->quantity)->toBe(0)
        ->and($later->fresh()->quantity)->toBe(7)
        ->and($expired->fresh()->quantity)->toBe(50)
        ->and(InventoryTransaction::query()->where('type', 'dispensed')->count())->toBe(2);
});

test('stock out form shows only essential release fields', function () {
    $user = userWithPermissions(['stock.release']);
    inventoryFixtures();

    $this->actingAs($user)
        ->get(route('stock.out'))
        ->assertOk()
        ->assertSee('for="medicine_id"', false)
        ->assertSee('for="quantity"', false)
        ->assertSee('for="quantity_unit"', false)
        ->assertSee('data-box-release-medicine', false)
        ->assertSee('id="box-quantity-reminder"', false)
        ->assertSee('for="type"', false)
        ->assertSee('for="purpose"', false)
        ->assertSee('for="recipient"', false)
        ->assertSee('data-dispense-type', false)
        ->assertSee('0 tablets left')
        ->assertDontSee('for="medicine_batch_id"', false)
        ->assertDontSee('for="transacted_at"', false)
        ->assertDontSee('for="reference_number"', false)
        ->assertDontSee('for="remarks"', false);
});

test('stock out converts boxes to base inventory units', function () {
    $user = userWithPermissions(['stock.release']);
    $fixtures = inventoryFixtures();
    $fixtures['medicine']->update(['box_size' => 100]);
    $batch = MedicineBatch::factory()->create([
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'quantity' => 300,
        'expiration_date' => today()->addYear(),
    ]);

    $this->actingAs($user)->post(route('stock.out.store'), [
        'medicine_id' => $fixtures['medicine']->id,
        'quantity' => 1,
        'quantity_unit' => 'box',
        'type' => 'stock_out',
        'purpose' => 'Clinic supply release',
    ])->assertRedirect();

    $transaction = InventoryTransaction::query()->where('medicine_batch_id', $batch->id)->latest('id')->firstOrFail();

    expect($batch->fresh()->quantity)->toBe(200)
        ->and($transaction->quantity)->toBe(100)
        ->and($transaction->metadata)->toMatchArray([
            'entered_quantity' => 1,
            'entered_unit' => 'box',
            'units_per_box' => 100,
        ]);
});

test('stock out blocks boxes when fewer than 100 units are available', function () {
    $user = userWithPermissions(['stock.release']);
    $fixtures = inventoryFixtures();
    $fixtures['medicine']->update(['box_size' => 10]);
    $batch = MedicineBatch::factory()->create([
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'quantity' => 99,
        'expiration_date' => today()->addYear(),
    ]);

    $this->actingAs($user)->post(route('stock.out.store'), [
        'medicine_id' => $fixtures['medicine']->id,
        'quantity' => 1,
        'quantity_unit' => 'box',
        'type' => 'stock_out',
        'purpose' => 'Clinic supply release',
    ])->assertSessionHasErrors('quantity_unit');

    expect($batch->fresh()->quantity)->toBe(99);
});

test('dispensing to a patient requires the patient name', function () {
    $user = userWithPermissions(['stock.release']);
    $fixtures = inventoryFixtures();
    $batch = MedicineBatch::factory()->create([
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'quantity' => 10,
        'expiration_date' => today()->addYear(),
    ]);

    $this->actingAs($user)
        ->post(route('stock.out.store'), [
            'medicine_id' => $fixtures['medicine']->id,
            'quantity' => 2,
            'type' => 'dispensed',
            'purpose' => 'Patient dispensing',
        ])
        ->assertSessionHasErrors('recipient');

    expect($batch->fresh()->quantity)->toBe(10);
});

test('expiration monitoring searches batches, medicines, suppliers, and locations', function () {
    $user = userWithPermissions(['medicines.view']);
    $fixtures = inventoryFixtures();
    $fixtures['medicine']->update(['generic_name' => 'Searchable Medicine']);
    $fixtures['supplier']->update(['name' => 'Searchable Supplier']);
    $fixtures['location']->update(['name' => 'Searchable Location']);

    MedicineBatch::factory()->create([
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'batch_number' => 'SEARCH-BATCH-001',
        'lot_number' => 'SEARCH-LOT-001',
        'quantity' => 10,
    ]);

    MedicineBatch::factory()->create(['batch_number' => 'HIDDEN-BATCH-001', 'quantity' => 10]);

    $this->actingAs($user)
        ->get(route('inventory.expirations', ['search' => 'SEARCH-BATCH']))
        ->assertOk()
        ->assertSee('SEARCH-BATCH-001')
        ->assertDontSee('HIDDEN-BATCH-001')
        ->assertSee('Search batches');
});

test('low stock alerts can be searched without changing summary totals', function () {
    $user = userWithPermissions(['medicines.view']);
    $fixtures = inventoryFixtures();
    $fixtures['medicine']->update(['generic_name' => 'Searchable Low Stock Medicine']);

    MedicineBatch::factory()->create([
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'quantity' => 2,
    ]);

    $otherMedicine = Medicine::factory()->create([
        'minimum_stock_level' => 10,
        'maximum_stock_level' => 100,
        'generic_name' => 'Other Low Stock Medicine',
    ]);
    MedicineBatch::factory()->create(['medicine_id' => $otherMedicine->id, 'quantity' => 1]);

    $this->actingAs($user)
        ->get(route('inventory.low-stock', ['search' => 'Searchable Low Stock']))
        ->assertOk()
        ->assertSee('Searchable Low Stock Medicine')
        ->assertDontSee('Other Low Stock Medicine')
        ->assertSee('Search low-stock medicines')
        ->assertSee('Out of stock');
});

test('low stock alerts display zero for a medicine without batches', function () {
    $user = userWithPermissions(['medicines.view']);
    Medicine::factory()->create([
        'generic_name' => 'Zero Stock Medicine',
        'unit' => 'tablets',
        'minimum_stock_level' => 10,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.low-stock', ['search' => 'Zero Stock Medicine']))
        ->assertOk()
        ->assertSee('Zero Stock Medicine')
        ->assertSee('>0</strong> tablets', false);
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

test('an adjustment converts boxes to base inventory units', function () {
    $user = userWithPermissions(['stock.adjust']);
    $fixtures = inventoryFixtures();
    $fixtures['medicine']->update(['box_size' => 50]);
    $batch = MedicineBatch::factory()->create([
        'medicine_id' => $fixtures['medicine']->id,
        'supplier_id' => $fixtures['supplier']->id,
        'storage_location_id' => $fixtures['location']->id,
        'quantity' => 200,
    ]);

    $this->actingAs($user)->post(route('stock.adjustment.store'), [
        'medicine_batch_id' => $batch->id,
        'quantity_difference' => -1,
        'quantity_unit' => 'box',
        'reason' => 'damaged',
        'notes' => 'One damaged box was removed.',
    ])->assertRedirect();

    $transaction = InventoryTransaction::query()->where('medicine_batch_id', $batch->id)->latest('id')->firstOrFail();

    expect($batch->fresh()->quantity)->toBe(150)
        ->and($transaction->metadata)->toMatchArray([
            'quantity_difference' => -50,
            'entered_quantity' => -1,
            'entered_unit' => 'box',
            'units_per_box' => 50,
        ]);
});
