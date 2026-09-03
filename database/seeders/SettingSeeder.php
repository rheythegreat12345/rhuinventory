<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'system_name' => ['MediStock RHU', 'string', 'general', true],
            'facility_name' => ['San Isidro Rural Health Unit', 'string', 'general', true],
            'contact_phone' => ['(049) 555-0147', 'string', 'general', false],
            'contact_email' => ['health@sanisidro.example', 'string', 'general', false],
            'address' => ['San Isidro Municipal Compound, Philippines', 'string', 'general', false],
            'low_stock_threshold' => ['10', 'integer', 'inventory', false],
            'expiration_warning_days' => ['90', 'integer', 'inventory', false],
            'date_format' => ['M d, Y', 'string', 'preferences', false],
            'timezone' => ['Asia/Manila', 'string', 'preferences', false],
            'notification_low_stock' => ['1', 'boolean', 'notifications', false],
            'notification_expiration' => ['1', 'boolean', 'notifications', false],
            'default_theme' => ['light', 'string', 'preferences', false],
        ];

        foreach ($settings as $key => [$value, $type, $group, $isPublic]) {
            Setting::query()->updateOrCreate(['key' => $key], compact('value', 'type', 'group') + ['is_public' => $isPublic]);
        }
    }
}
