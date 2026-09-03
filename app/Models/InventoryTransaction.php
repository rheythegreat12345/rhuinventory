<?php

namespace App\Models;

use App\TransactionType;
use Database\Factories\InventoryTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryTransaction extends Model
{
    /** @use HasFactory<InventoryTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'transaction_code', 'medicine_id', 'medicine_batch_id', 'user_id', 'type',
        'quantity', 'previous_stock', 'new_stock', 'recipient', 'purpose', 'transacted_at',
        'reference_number', 'reason', 'remarks', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'transacted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adjustment(): HasOne
    {
        return $this->hasOne(StockAdjustment::class);
    }
}
