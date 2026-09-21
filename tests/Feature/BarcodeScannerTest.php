<?php

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;

test('a barcode lookup redirects to the matching medicine without changing its record', function () {
    $user = userWithPermissions(['medicines.view']);
    $medicine = Medicine::factory()->create(['barcode' => '4800000000001']);
    $originalMedicine = $medicine->only(['id', 'barcode', 'generic_name', 'medicine_code']);
    $csrfToken = 'barcode-scanner-test-token';

    $this->withSession(['_token' => $csrfToken])
        ->actingAs($user)
        ->post(route('scanner.lookup'), ['barcode' => $medicine->barcode, '_token' => $csrfToken])
        ->assertRedirect(route('medicines.show', $medicine));

    expect($medicine->fresh()->only(['id', 'barcode', 'generic_name', 'medicine_code']))->toBe($originalMedicine);
});

test('an unmatched barcode is kept in the scanner field for correction', function () {
    $user = userWithPermissions(['medicines.view']);
    $csrfToken = 'barcode-scanner-invalid-test-token';

    $this->from(route('scanner'))
        ->withSession(['_token' => $csrfToken])
        ->actingAs($user)
        ->post(route('scanner.lookup'), [
            'barcode' => '4800000000999',
            '_token' => $csrfToken,
        ])
        ->assertRedirect(route('scanner'))
        ->assertSessionHasInput('barcode', '4800000000999')
        ->assertSessionHasErrors(['barcode']);
});

test('a barcode scan can open restock with the matching medicine selected', function () {
    $user = userWithPermissions(['stock.receive']);
    $medicine = Medicine::factory()->create(['barcode' => '4800000000002']);
    $csrfToken = 'barcode-stock-in-test-token';

    $this->withSession(['_token' => $csrfToken])
        ->actingAs($user)
        ->post(route('scanner.lookup'), [
            'barcode' => $medicine->barcode,
            'scan_action' => 'restock',
            '_token' => $csrfToken,
        ])
        ->assertRedirect(route('stock.in', ['medicine' => $medicine, 'restock' => 1]));

    expect($medicine->fresh()->barcode)->toBe('4800000000002');
});

test('a scan-safe EAN-13 label resolves to its invalid legacy demo barcode without changing the database', function () {
    $user = userWithPermissions(['medicines.view']);
    $medicine = Medicine::factory()->create(['barcode' => '4801000000004']);
    $csrfToken = 'barcode-scanner-normalized-test-token';

    $this->withSession(['_token' => $csrfToken])
        ->actingAs($user)
        ->post(route('scanner.lookup'), ['barcode' => '4801000000009', '_token' => $csrfToken])
        ->assertRedirect(route('medicines.show', $medicine));

    expect($medicine->fresh()->barcode)->toBe('4801000000004');
});

test('an unknown barcode can open new medicine stock-in registration', function () {
    $user = userWithPermissions(['medicines.create', 'stock.receive']);
    $csrfToken = 'barcode-new-medicine-test-token';

    $this->withSession(['_token' => $csrfToken])
        ->actingAs($user)
        ->post(route('scanner.lookup'), [
            'barcode' => '4800000000003',
            'scan_action' => 'new_medicine',
            '_token' => $csrfToken,
        ])
        ->assertRedirect(route('scanner.register', ['barcode' => '4800000000003']));
});

test('new medicine stock-in registration shows one stock threshold section', function () {
    $user = userWithPermissions(['medicines.create', 'stock.receive']);

    $response = $this->actingAs($user)
        ->get(route('scanner.register', ['barcode' => '4800000000004']));

    $response->assertOk();

    expect(substr_count($response->getContent(), 'Stock thresholds'))->toBe(1)
        ->and($response->getContent())->not->toContain('Dosage form')
        ->and($response->getContent())->not->toContain('Dosage / instructions')
        ->and($response->getContent())->not->toContain('Storage location')
        ->and($response->getContent())->not->toContain('Maximum / target stock')
        ->and($response->getContent())->not->toContain('Storage condition');
});

test('new medicine scanner registration requires selecting a supplier', function () {
    $user = userWithPermissions(['medicines.create', 'stock.receive']);
    Supplier::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->get(route('scanner.register', ['barcode' => '4800000000099']))
        ->assertOk()
        ->assertSee('for="supplier_id"', false)
        ->assertSee('Choose supplier');
});

test('scanner registration saves a medicine, batch, and transaction together with a unique medicine code', function () {
    $user = userWithPermissions(['medicines.create', 'stock.receive']);
    $category = MedicineCategory::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $location = StorageLocation::factory()->create(['is_active' => true]);
    $barcode = '4800000000011';

    $this->actingAs($user)
        ->post(route('scanner.register.store'), [
            'barcode' => $barcode,
            'generic_name' => 'Scanner Registered Medicine',
            'medicine_category_id' => $category->id,
            'unit' => 'tablets',
            'quantity' => 12,
            'expiration_date' => today()->addYear()->toDateString(),
            'arrival_date' => today()->toDateString(),
            'supplier_id' => $supplier->id,
            'unit_cost' => '2.50',
            'minimum_stock_level' => 10,
        ])
        ->assertRedirect();

    $medicine = Medicine::query()->where('barcode', $barcode)->firstOrFail();

    expect($medicine->medicine_code)->toStartWith('MED-')
        ->and(strlen($medicine->medicine_code))->toBe(30)
        ->and($medicine->dosage_form)->toBe('Unspecified')
        ->and($medicine->dosage)->toBeNull()
        ->and($medicine->storage_condition)->toBeNull()
        ->and($medicine->maximum_stock_level)->toBe(100)
        ->and(MedicineBatch::query()->where('medicine_id', $medicine->id)->value('quantity'))->toBe(12)
        ->and(MedicineBatch::query()->where('medicine_id', $medicine->id)->value('supplier_id'))->toBe($supplier->id)
        ->and(MedicineBatch::query()->where('medicine_id', $medicine->id)->value('storage_location_id'))->toBe($location->id);
    $this->assertDatabaseHas('inventory_transactions', ['medicine_id' => $medicine->id, 'quantity' => 12, 'type' => 'stock_in']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'medicine_created', 'auditable_id' => $medicine->id]);
    $this->assertDatabaseHas('inventory_notifications', ['type' => 'stock_received']);
});

test('scanner registration derives a safe target stock level from the minimum stock level', function () {
    $user = userWithPermissions(['medicines.create', 'stock.receive']);
    $category = MedicineCategory::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $location = StorageLocation::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->post(route('scanner.register.store'), [
            'barcode' => '4800000000012',
            'generic_name' => 'High Minimum Stock Medicine',
            'medicine_category_id' => $category->id,
            'unit' => 'tablets',
            'quantity' => 12,
            'expiration_date' => today()->addYear()->toDateString(),
            'arrival_date' => today()->toDateString(),
            'supplier_id' => $supplier->id,
            'minimum_stock_level' => 200,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('medicines', [
        'barcode' => '4800000000012',
        'minimum_stock_level' => 200,
        'maximum_stock_level' => 200,
    ]);
    $this->assertDatabaseHas('medicine_batches', [
        'medicine_id' => Medicine::query()->where('barcode', '4800000000012')->value('id'),
        'storage_location_id' => $location->id,
    ]);
});

test('the scanner page uses a barcode reference and retains hardware scanner controls', function () {
    $user = userWithPermissions(['stock.receive']);

    $this->actingAs($user)
        ->get(route('scanner'))
        ->assertOk()
        ->assertSee('id="barcode-visual"', false)
        ->assertSee('id="barcode-svg"', false)
        ->assertSee('renderBarcode', false)
        ->assertSee('renderEan13', false)
        ->assertSee('EAN-13 barcode lines', false)
        ->assertSee('Code 128 barcode', false)
        ->assertDontSee('Camera scanner')
        ->assertDontSee('id="scanner-video"', false)
        ->assertDontSee('id="camera-select"', false)
        ->assertDontSee('class="icon-button scanner-shortcut desktop-only"', false)
        ->assertSee('Scan / Restock')
        ->assertDontSee('>Stock In<', false)
        ->assertDontSee('>Stock Out / Dispense<', false)
        ->assertDontSee('BrowserMultiFormatReader', false)
        ->assertDontSee('decodeFromVideoDevice', false)
        ->assertSee('id="barcode-form"', false)
        ->assertSee('id="hardware-scanner-status"', false)
        ->assertSee('id="detect-usb-scanner"', false)
        ->assertSee('name="scan_action"', false)
        ->assertSee('Stock in new medicine')
        ->assertSee('Restock this medicine');
});

test('restock mode shows the scanned medicine stock and reorder recommendation', function () {
    $user = userWithPermissions(['stock.receive']);
    $medicine = Medicine::factory()->create([
        'minimum_stock_level' => 10,
        'maximum_stock_level' => 100,
    ]);

    $this->actingAs($user)
        ->get(route('stock.in', ['medicine' => $medicine, 'restock' => 1]))
        ->assertOk()
        ->assertSee('Restock mode: '.$medicine->generic_name)
        ->assertSee('Recommended replenishment:');
});

test('scanner dispensing requires a recipient before deducting stock', function () {
    $user = userWithPermissions(['stock.release']);
    $medicine = Medicine::factory()->create(['unit' => 'tablets']);
    $batch = MedicineBatch::factory()->create([
        'medicine_id' => $medicine->id,
        'quantity' => 10,
        'expiration_date' => today()->addYear(),
    ]);

    $this->actingAs($user)
        ->post(route('scanner.dispense.store', $medicine), [
            'quantity' => 2,
            'purpose' => 'Patient dispensing',
        ])
        ->assertSessionHasErrors('recipient');

    expect($batch->fresh()->quantity)->toBe(10);
});
