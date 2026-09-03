<?php

namespace App\Models;

use Database\Factories\StorageLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageLocation extends Model
{
    /** @use HasFactory<StorageLocationFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }
}
