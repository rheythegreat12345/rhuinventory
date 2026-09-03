<?php

namespace App\Models;

use Database\Factories\InventoryNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryNotification extends Model
{
    /** @use HasFactory<InventoryNotificationFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'type', 'title', 'message', 'level', 'data', 'read_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
