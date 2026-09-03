<?php

namespace Database\Seeders;

use App\Models\InventoryNotification;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use Illuminate\Database\Seeder;

class InventoryNotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Medicine::query()->withInventory()->get()->filter(fn (Medicine $medicine) => $medicine->stockStatus() !== 'normal')->each(function (Medicine $medicine): void {
            InventoryNotification::query()->create([
                'type' => 'stock_'.$medicine->stockStatus(),
                'title' => str($medicine->stockStatus())->headline().' stock alert',
                'message' => "{$medicine->generic_name} has {$medicine->current_stock} {$medicine->unit} remaining.",
                'level' => $medicine->stockStatus() === 'low' ? 'warning' : 'danger',
                'data' => ['medicine_id' => $medicine->id, 'url' => route('medicines.show', $medicine)],
            ]);
        });

        MedicineBatch::query()->with('medicine')->where('quantity', '>', 0)->whereDate('expiration_date', '<=', today()->addDays(30))->get()->each(function (MedicineBatch $batch): void {
            InventoryNotification::query()->create([
                'type' => $batch->expiration_date->isPast() ? 'expired' : 'expiring',
                'title' => $batch->expiration_date->isPast() ? 'Expired batch detected' : 'Batch expiring soon',
                'message' => "{$batch->medicine->generic_name} batch {$batch->batch_number} expires on {$batch->expiration_date->format('M d, Y')}.",
                'level' => $batch->expiration_date->isPast() ? 'danger' : 'warning',
                'data' => ['medicine_id' => $batch->medicine_id, 'batch_id' => $batch->id, 'url' => route('medicines.show', $batch->medicine)],
            ]);
        });
    }
}
