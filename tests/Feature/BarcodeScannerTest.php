<?php

use App\Models\Medicine;

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

test('a barcode scan can open stock in with the matching medicine selected', function () {
    $user = userWithPermissions(['stock.receive']);
    $medicine = Medicine::factory()->create(['barcode' => '4800000000002']);
    $csrfToken = 'barcode-stock-in-test-token';

    $this->withSession(['_token' => $csrfToken])
        ->actingAs($user)
        ->post(route('scanner.lookup'), [
            'barcode' => $medicine->barcode,
            'scan_action' => 'stock_in',
            '_token' => $csrfToken,
        ])
        ->assertRedirect(route('stock.in', ['medicine' => $medicine]));

    expect($medicine->fresh()->barcode)->toBe('4800000000002');
});

test('the scanner page includes camera and hardware scanner controls', function () {
    $user = userWithPermissions(['stock.receive']);

    $this->actingAs($user)
        ->get(route('scanner'))
        ->assertOk()
        ->assertSee('id="scanner-video"', false)
        ->assertSee('id="camera-select"', false)
        ->assertSee('class="icon-button scanner-shortcut desktop-only"', false)
        ->assertSee('BrowserMultiFormatReader', false)
        ->assertSee('decodeFromVideoDevice', false)
        ->assertSee('id="barcode-form"', false)
        ->assertSee('id="hardware-scanner-status"', false)
        ->assertSee('id="detect-usb-scanner"', false)
        ->assertSee('name="scan_action"', false);
});
