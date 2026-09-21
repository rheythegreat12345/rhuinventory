<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryNotificationRead extends Model
{
    protected $fillable = ['inventory_notification_id', 'user_id', 'read_at', 'deleted_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(InventoryNotification::class, 'inventory_notification_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
