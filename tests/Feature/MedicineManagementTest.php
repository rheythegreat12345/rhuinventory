<?php

use App\Models\Medicine;
use App\Models\MedicineCategory;
use Illuminate\Http\UploadedFile;

test('authorized staff can create and update a medicine', function () {
    $user = userWithPermissions(['medicines.create', 'medicines.edit', 'medicines.view']);
    $category = MedicineCategory::factory()->create();
    $data = [
        'medicine_category_id' => $category->id,
        'medicine_code' => 'MED-TEST-1',
        'barcode' => '4800000000001',
        'generic_name' => 'Test Medicine',
        'brand_name' => 'Sample Brand',
        'dosage' => '1 tablet',
        'strength' => '100 mg',
        'dosage_form' => 'Tablet',
        'unit' => 'tablets',
        'box_size' => 100,
        'minimum_stock_level' => 10,
        'maximum_stock_level' => 100,
        'reorder_level' => 20,
        'unit_cost' => 2.5,
        'reference_price' => 3,
        'storage_condition' => 'Store below 30°C',
        'description' => 'Inventory test record.',
        'status' => 'active',
    ];

    $this->actingAs($user)->post(route('medicines.store'), $data)->assertRedirect();
    $medicine = Medicine::query()->where('medicine_code', 'MED-TEST-1')->firstOrFail();

    $this->actingAs($user)->put(route('medicines.update', $medicine), [...$data, 'generic_name' => 'Updated Medicine'])
        ->assertRedirect(route('medicines.show', $medicine));

    expect($medicine->fresh()->generic_name)->toBe('Updated Medicine')
        ->and($medicine->fresh()->box_size)->toBe(100);
    $this->assertDatabaseHas('audit_logs', ['action' => 'medicine_updated', 'auditable_id' => $medicine->id]);
});

test('duplicate medicine identifiers are rejected', function () {
    $user = userWithPermissions(['medicines.create']);
    $category = MedicineCategory::factory()->create();
    Medicine::factory()->create(['medicine_category_id' => $category->id, 'medicine_code' => 'DUP-001']);

    $this->actingAs($user)->post(route('medicines.store'), [
        'medicine_category_id' => $category->id,
        'medicine_code' => 'DUP-001',
        'generic_name' => 'Duplicate',
        'dosage_form' => 'Tablet',
        'unit' => 'tablets',
        'minimum_stock_level' => 10,
        'maximum_stock_level' => 100,
        'reorder_level' => 20,
        'unit_cost' => 1,
        'status' => 'active',
    ])->assertSessionHasErrors('medicine_code');
});

test('authorized staff can export the current medicine list as a CSV download', function () {
    $user = userWithPermissions(['medicines.view']);
    $medicine = Medicine::factory()->create(['medicine_code' => 'MED-EXPORT-1', 'generic_name' => 'Export Medicine']);

    $response = $this->actingAs($user)->get(route('medicines.export'));

    $response
        ->assertOk()
        ->assertDownload('medicine-inventory-'.today()->format('Y-m-d').'.csv');

    expect($response->streamedContent())
        ->toContain('Medicine ID')
        ->toContain($medicine->medicine_code)
        ->toContain($medicine->generic_name);
});

test('authorized staff can archive selected medicines together', function () {
    $user = userWithPermissions(['medicines.view', 'medicines.edit']);
    $medicines = Medicine::factory()->count(2)->create();

    $this->actingAs($user)
        ->get(route('medicines.index'))
        ->assertOk()
        ->assertSee('data-medicine-bulk-selection', false)
        ->assertSee('Manage medicines')
        ->assertDontSee('Bulk import medicines')
        ->assertSee('Archive selected');

    $this->actingAs($user)
        ->from(route('medicines.index'))
        ->delete(route('medicines.bulk-archive'), ['medicine_ids' => $medicines->pluck('id')->all()])
        ->assertRedirect(route('medicines.index'))
        ->assertSessionHas('success', 'Archived 2 medicines. Their transaction history was preserved.');

    $medicines->each(function (Medicine $medicine): void {
        $this->assertSoftDeleted('medicines', ['id' => $medicine->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'medicine_archived', 'auditable_id' => $medicine->id]);
    });
});

test('a medicine page shows a scan-ready barcode preview', function () {
    $user = userWithPermissions(['medicines.view', 'stock.receive']);
    $medicine = Medicine::factory()->create(['barcode' => '4801000000009']);

    $this->actingAs($user)
        ->get(route('medicines.show', $medicine))
        ->assertOk()
        ->assertSee('id="medicine-barcode-svg"', false)
        ->assertSee('data-barcode-preview', false)
        ->assertSee('data-barcode-value="4801000000009"', false)
        ->assertSee('This barcode can be scanned from this screen or a printed label.')
        ->assertSee('Restock')
        ->assertDontSee('Receive batch');
});

test('an invalid numeric demo barcode displays as a valid scan-safe EAN-13 value without changing storage', function () {
    $user = userWithPermissions(['medicines.view']);
    $medicine = Medicine::factory()->create(['barcode' => '4801000000004']);

    $this->actingAs($user)
        ->get(route('medicines.show', $medicine))
        ->assertOk()
        ->assertSee('data-barcode-value="4801000000009"', false)
        ->assertSee('4801000000009');

    expect($medicine->fresh()->barcode)->toBe('4801000000004');
});

test('medicine import rejects stock thresholds that would create an invalid reorder setup', function () {
    $user = userWithPermissions(['medicines.create']);
    $category = MedicineCategory::factory()->create(['code' => 'IMPORT']);
    $headers = 'medicine_code,generic_name,brand_name,category_code,dosage,strength,dosage_form,unit,minimum_stock_level,maximum_stock_level,reorder_level,unit_cost,reference_price,storage_condition,description';
    $row = "MED-IMPORT-INVALID,Imported Medicine,,{$category->code},,500 mg,Tablet,tablets,50,20,25,1.50,,,";
    $file = UploadedFile::fake()->createWithContent('invalid-medicines.csv', "{$headers}\n{$row}");

    $this->actingAs($user)
        ->post(route('medicines.import'), ['import_file' => $file])
        ->assertSessionHasErrors('import_file');

    $this->assertDatabaseMissing('medicines', ['medicine_code' => 'MED-IMPORT-INVALID']);
});
