<?php

namespace Database\Seeders;

use App\Models\Medicine;
use App\Models\MedicineCategory;
use Illuminate\Database\Seeder;

class MedicineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $medicines = [
            ['Paracetamol', 'Biogesic', 'ANTIP', '500 mg', 'Tablet', 'tablets', 1.20],
            ['Amoxicillin', 'Amoxil', 'ANTI', '500 mg', 'Capsule', 'capsules', 4.50],
            ['Amlodipine', 'Norvasc', 'HTN', '5 mg', 'Tablet', 'tablets', 2.10],
            ['Cetirizine', 'Zyrtec', 'HIST', '10 mg', 'Tablet', 'tablets', 2.00],
            ['Metformin', 'Glucophage', 'DM', '500 mg', 'Tablet', 'tablets', 2.75],
            ['Ascorbic Acid', 'Ceelin', 'VIT', '500 mg', 'Tablet', 'tablets', 1.85],
            ['Ibuprofen', 'Advil', 'ANAL', '200 mg', 'Tablet', 'tablets', 3.25],
            ['Losartan', 'Lifezar', 'HTN', '50 mg', 'Tablet', 'tablets', 4.10],
            ['Azithromycin', 'Zithromax', 'ANTI', '500 mg', 'Tablet', 'tablets', 22.50],
            ['Salbutamol', 'Ventolin', 'EMER', '2 mg/5 mL', 'Syrup', 'bottles', 68.00],
            ['Omeprazole', 'Losec', 'GI', '20 mg', 'Capsule', 'capsules', 5.20],
            ['Oral Rehydration Salts', 'Hydrite', 'OTHER', '20.5 g', 'Powder sachet', 'sachets', 8.50],
            ['Ferrous Sulfate', 'Hemarate', 'VIT', '325 mg', 'Tablet', 'tablets', 1.55],
            ['Loratadine', 'Claritin', 'HIST', '10 mg', 'Tablet', 'tablets', 3.40],
            ['Captopril', 'Capoten', 'HTN', '25 mg', 'Tablet', 'tablets', 2.80],
            ['Cefalexin', 'Keflex', 'ANTI', '500 mg', 'Capsule', 'capsules', 7.90],
            ['Mefenamic Acid', 'Dolfenal', 'ANAL', '500 mg', 'Capsule', 'capsules', 5.60],
            ['Glibenclamide', 'Daonil', 'DM', '5 mg', 'Tablet', 'tablets', 2.35],
            ['Epinephrine', 'Adrenalin', 'EMER', '1 mg/mL', 'Ampoule', 'ampoules', 85.00],
            ['Zinc Sulfate', 'ZincPlus', 'VIT', '20 mg', 'Tablet', 'tablets', 2.25],
        ];

        foreach ($medicines as $index => [$genericName, $brandName, $categoryCode, $strength, $dosageForm, $unit, $unitCost]) {
            Medicine::query()->updateOrCreate(['medicine_code' => 'MED-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)], [
                'medicine_category_id' => MedicineCategory::query()->where('code', $categoryCode)->value('id'),
                'barcode' => '480'.str_pad((string) (1000000000 + $index), 10, '0', STR_PAD_LEFT),
                'generic_name' => $genericName,
                'brand_name' => $brandName,
                'dosage' => $dosageForm === 'Syrup' ? 'As directed' : '1 unit',
                'strength' => $strength,
                'dosage_form' => $dosageForm,
                'unit' => $unit,
                'minimum_stock_level' => 10 + (($index % 3) * 5),
                'maximum_stock_level' => 150 + (($index % 4) * 50),
                'reorder_level' => 25 + (($index % 3) * 5),
                'unit_cost' => $unitCost,
                'reference_price' => round($unitCost * 1.15, 2),
                'storage_condition' => $genericName === 'Epinephrine' ? 'Store at 2–8°C; protect from light.' : 'Store below 30°C in a dry place.',
                'description' => 'Sample inventory record for system demonstration only; not medical advice.',
                'status' => 'active',
            ]);
        }
    }
}
