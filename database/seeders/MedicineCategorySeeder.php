<?php

namespace Database\Seeders;

use App\Models\MedicineCategory;
use Illuminate\Database\Seeder;

class MedicineCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['ANTI', 'Antibiotics', 'Medicines used to treat bacterial infections.'],
            ['ANAL', 'Analgesics', 'Pain relief medicines.'],
            ['ANTIP', 'Antipyretics', 'Fever-reducing medicines.'],
            ['HTN', 'Antihypertensives', 'Blood pressure management medicines.'],
            ['VIT', 'Vitamins', 'Vitamin and mineral preparations.'],
            ['HIST', 'Antihistamines', 'Allergy symptom medicines.'],
            ['DM', 'Antidiabetics', 'Diabetes management medicines.'],
            ['EMER', 'Emergency Medicines', 'Medicines used in urgent care.'],
            ['GI', 'Gastrointestinal', 'Digestive system medicines.'],
            ['OTHER', 'Other', 'Other essential medicines and supplies.'],
        ] as [$code, $name, $description]) {
            MedicineCategory::query()->updateOrCreate(['code' => $code], compact('name', 'description') + ['is_active' => true]);
        }
    }
}
