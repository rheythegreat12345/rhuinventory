<?php

namespace Database\Seeders;

use App\Models\StorageLocation;
use Illuminate\Database\Seeder;

class StorageLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['MAIN-A', 'Main Pharmacy · Shelf A', 'Tablet and capsule storage'],
            ['MAIN-B', 'Main Pharmacy · Shelf B', 'Syrup and suspension storage'],
            ['COLD-01', 'Cold Storage · Refrigerator 1', 'Temperature-controlled storage'],
            ['EMER-01', 'Emergency Room Cabinet', 'Urgent care medicine stock'],
            ['SAT-01', 'Satellite Clinic Stockroom', 'Secondary RHU location'],
        ] as [$code, $name, $description]) {
            StorageLocation::query()->updateOrCreate(['code' => $code], compact('name', 'description') + ['is_active' => true]);
        }
    }
}
