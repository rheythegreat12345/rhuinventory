<?php

namespace Database\Seeders;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StorageLocation;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class MedicineBatchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = Supplier::query()->orderBy('id')->get();
        $locations = StorageLocation::query()->orderBy('id')->get();
        $expirationDays = [240, 210, 180, -12, 5, 22, 46, 76, 120, 155, 190, 225, 260, 295, 330, 365, 400, 435, 470, 505];

        Medicine::query()->orderBy('id')->get()->each(function (Medicine $medicine, int $index) use ($suppliers, $locations, $expirationDays): void {
            $baseQuantity = match ($index) {
                0 => 0,
                1 => 2,
                2 => 7,
                default => 24 + (($index * 11) % 86),
            };

            MedicineBatch::query()->create([
                'medicine_id' => $medicine->id,
                'supplier_id' => $suppliers[$index % $suppliers->count()]->id,
                'storage_location_id' => $locations[$index % $locations->count()]->id,
                'batch_number' => 'B26-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT).'-A',
                'lot_number' => 'LOT-26'.str_pad((string) ($index + 101), 4, '0', STR_PAD_LEFT),
                'manufacturing_date' => today()->subMonths(8 + ($index % 5)),
                'expiration_date' => today()->addDays($expirationDays[$index]),
                'quantity' => $baseQuantity,
                'unit_cost' => $medicine->unit_cost,
                'received_at' => today()->subDays(15 + ($index * 2)),
                'status' => 'active',
            ]);

            if ($index >= 4 && $index % 2 === 0) {
                MedicineBatch::query()->create([
                    'medicine_id' => $medicine->id,
                    'supplier_id' => $suppliers[($index + 1) % $suppliers->count()]->id,
                    'storage_location_id' => $locations[($index + 1) % $locations->count()]->id,
                    'batch_number' => 'B26-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT).'-B',
                    'lot_number' => 'LOT-26'.str_pad((string) ($index + 501), 4, '0', STR_PAD_LEFT),
                    'manufacturing_date' => today()->subMonths(4),
                    'expiration_date' => today()->addDays($expirationDays[$index] + 150),
                    'quantity' => 18 + ($index % 20),
                    'unit_cost' => (float) $medicine->unit_cost + 0.25,
                    'received_at' => today()->subDays(8 + $index),
                    'status' => 'active',
                ]);
            }
        });
    }
}
