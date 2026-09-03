<?php

namespace App\Models;

use Database\Factories\MedicineCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicineCategory extends Model
{
    /** @use HasFactory<MedicineCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function medicines(): HasMany
    {
        return $this->hasMany(Medicine::class);
    }
}
