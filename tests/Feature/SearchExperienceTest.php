<?php

use App\Models\Medicine;
use App\Models\MedicineCategory;

test('global search returns matching live results', function () {
    $user = userWithPermissions(['medicines.view']);
    $antibioticCategory = MedicineCategory::factory()->create(['name' => 'Antibiotics']);
    $reliefCategory = MedicineCategory::factory()->create(['name' => 'Relief']);
    Medicine::factory()->create([
        'medicine_category_id' => $antibioticCategory->id,
        'generic_name' => 'Amoxicillin',
        'brand_name' => 'Moxilin',
        'medicine_code' => 'MED-AMOX-01',
    ]);
    Medicine::factory()->create([
        'medicine_category_id' => $reliefCategory->id,
        'generic_name' => 'Ibuprofen',
        'brand_name' => 'Brufen',
        'medicine_code' => 'MED-IBU-01',
    ]);

    $this->actingAs($user)
        ->getJson(route('search', ['q' => 'A']))
        ->assertOk()
        ->assertJsonFragment(['label' => 'Amoxicillin'])
        ->assertJsonMissing(['label' => 'Ibuprofen']);
});

test('global search is unavailable to users without medicine viewing permission', function () {
    $user = userWithPermissions();
    Medicine::factory()->create(['generic_name' => 'Restricted Medicine']);

    $this->actingAs($user)
        ->getJson(route('search', ['q' => 'Restricted']))
        ->assertForbidden();
});

test('dashboard keeps statistic text separated without a header search bar', function () {
    $user = userWithPermissions();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('data-live-global-search', false)
        ->assertSee('class="stat-copy"', false)
        ->assertSee('class="stat-value"', false);
});
