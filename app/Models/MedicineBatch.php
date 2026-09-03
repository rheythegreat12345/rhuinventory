<?php

namespace App\Models;

use Database\Factories\MedicineBatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicineBatch extends Model
{
    /** @use HasFactory<MedicineBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'medicine_id', 'supplier_id', 'storage_location_id', 'batch_number', 'lot_number',
        'manufacturing_date', 'expiration_date', 'quantity', 'unit_cost', 'received_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiration_date' => 'date',
            'received_at' => 'date',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function scopeAvailableFefo(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0)
            ->where('status', 'active')
            ->whereDate('expiration_date', '>=', today())
            ->orderBy('expiration_date')
            ->orderBy('received_at');
    }

    public function expirationStatus(): string
    {
        $days = today()->diffInDays($this->expiration_date, false);

        return match (true) {
            $days < 0 => 'expired',
            $days <= 7 => '7_days',
            $days <= 30 => '30_days',
            $days <= 60 => '60_days',
            $days <= 90 => '90_days',
            default => 'safe',
        };
    }
}
