<?php

namespace App\Services;

use App\Models\InventoryNotification;
use App\Models\Medicine;
use App\Models\MedicineBatch;

class AlertService
{
    public function syncMedicine(Medicine $medicine): void
    {
        $medicine->loadSum('batches as current_stock', 'quantity');
        $status = $medicine->stockStatus();

        if (in_array($status, ['low', 'critical', 'out'], true)) {
            $this->createUnique(
                type: "stock_{$status}",
                title: match ($status) {
                    'out' => 'Medicine out of stock',
                    'critical' => 'Critical stock level',
                    default => 'Low stock warning',
                },
                message: "{$medicine->generic_name} has {$medicine->current_stock} {$medicine->unit} remaining.",
                level: $status === 'low' ? 'warning' : 'danger',
                data: ['medicine_id' => $medicine->id, 'url' => route('medicines.show', $medicine)],
            );
        } else {
            InventoryNotification::query()
                ->whereIn('type', ['stock_low', 'stock_critical', 'stock_out'])
                ->where('data->medicine_id', $medicine->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
    }

    public function syncExpirations(): void
    {
        MedicineBatch::query()
            ->with('medicine')
            ->where('quantity', '>', 0)
            ->whereDate('expiration_date', '<=', today()->addDays(90))
            ->each(function (MedicineBatch $batch): void {
                $expired = $batch->expiration_date->isBefore(today());
                $this->createUnique(
                    type: $expired ? 'expired' : 'expiring',
                    title: $expired ? 'Expired batch detected' : 'Batch expiring soon',
                    message: "{$batch->medicine->generic_name} batch {$batch->batch_number} ".($expired ? 'expired' : 'expires')." on {$batch->expiration_date->format('M d, Y')}.",
                    level: $expired ? 'danger' : 'warning',
                    data: ['medicine_id' => $batch->medicine_id, 'batch_id' => $batch->id, 'url' => route('medicines.show', $batch->medicine)],
                );
            });
    }

    /** @param array<string, mixed> $data */
    public function createUnique(string $type, string $title, string $message, string $level, array $data): void
    {
        InventoryNotification::query()->firstOrCreate(
            [
                'type' => $type,
                'data->medicine_id' => $data['medicine_id'] ?? null,
                'data->batch_id' => $data['batch_id'] ?? null,
                'read_at' => null,
            ],
            ['title' => $title, 'message' => $message, 'level' => $level, 'data' => $data],
        );
    }
}
