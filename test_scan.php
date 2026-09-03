<?php

use App\Models\Medicine;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Test barcode lookup
$barcode = '4801000000000';
$medicineCode = 'MED-001';

echo "Testing barcode lookup for: $barcode\n";
$medicine = Medicine::where('barcode', $barcode)->orWhere('medicine_code', $barcode)->first();
if ($medicine) {
    echo '✓ Found medicine: '.$medicine->generic_name.' (Code: '.$medicine->medicine_code.")\n";
} else {
    echo "✗ Medicine not found\n";
}

echo "\nTesting medicine code lookup for: $medicineCode\n";
$medicine = Medicine::where('barcode', $medicineCode)->orWhere('medicine_code', $medicineCode)->first();
if ($medicine) {
    echo '✓ Found medicine: '.$medicine->generic_name.' (Code: '.$medicine->medicine_code.")\n";
} else {
    echo "✗ Medicine not found\n";
}

echo "\nTesting non-existent code: INVALID123\n";
$medicine = Medicine::where('barcode', 'INVALID123')->orWhere('medicine_code', 'INVALID123')->first();
if ($medicine) {
    echo "✗ Unexpectedly found medicine\n";
} else {
    echo "✓ Correctly returned no result\n";
}
