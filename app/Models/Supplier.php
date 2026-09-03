<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'contact_person', 'phone', 'email', 'address',
        'license_number', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }
}
