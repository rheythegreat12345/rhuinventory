<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            ['SUP-001', 'DOH Regional Medical Depot', 'Maria Santos', '(02) 8651 7800', 'depot@doh.gov.ph', 'Regional Government Center', 'DOH-RMD-2026-01'],
            ['SUP-002', 'MedCore Distribution Inc.', 'Joshua Reyes', '0917 555 0182', 'orders@medcore.example', 'Quezon City, Metro Manila', 'FDA-WHO-28419'],
            ['SUP-003', 'Bayanihan Pharma Supply', 'Angela Cruz', '0918 442 7710', 'care@bayanihan.example', 'Makati City, Metro Manila', 'FDA-LTO-82940'],
            ['SUP-004', 'HealthFirst Generics', 'Paolo Garcia', '0920 194 3381', 'supply@healthfirst.example', 'Pasig City, Metro Manila', 'FDA-LTO-44182'],
            ['SUP-005', 'Community Care Medical Traders', 'Lea Mendoza', '0916 620 4811', 'service@ccmt.example', 'Calamba City, Laguna', 'FDA-LTO-73014'],
        ];

        foreach ($suppliers as [$code, $name, $contactPerson, $phone, $email, $address, $licenseNumber]) {
            Supplier::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'contact_person' => $contactPerson,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
                'license_number' => $licenseNumber,
                'is_active' => true,
                'notes' => 'Approved supplier for RHU inventory operations.',
            ]);
        }
    }
}
