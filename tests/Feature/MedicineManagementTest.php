<?php

use App\Models\Medicine;
use App\Models\MedicineCategory;

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

    expect($medicine->fresh()->generic_name)->toBe('Updated Medicine');
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
