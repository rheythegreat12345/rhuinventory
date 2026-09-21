<?php

namespace App\Models;

use Database\Factories\MedicineFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    /** @use HasFactory<MedicineFactory> */
    use HasFactory, SoftDeletes;

    public const MINIMUM_UNITS_FOR_BOX_RELEASE = 100;

    protected $fillable = [
        'medicine_category_id', 'medicine_code', 'barcode', 'generic_name', 'brand_name',
        'dosage', 'strength', 'dosage_form', 'unit', 'box_size', 'minimum_stock_level',
        'maximum_stock_level', 'reorder_level', 'unit_cost', 'reference_price',
        'storage_condition', 'description', 'status',
    ];

    protected function casts(): array
    {
        return [
            'box_size' => 'integer',
            'unit_cost' => 'decimal:2',
            'reference_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MedicineCategory::class, 'medicine_category_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function scopeWithInventory(Builder $query): Builder
    {
        return $query
            ->withSum('batches as current_stock', 'quantity')
            ->withSum(['batches as usable_stock' => fn (Builder $batchQuery) => $batchQuery
                ->where('status', 'active')
                ->whereDate('expiration_date', '>=', today())], 'quantity');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $medicineQuery, string $term): void {
            $medicineQuery->where(function (Builder $nestedQuery) use ($term): void {
                $nestedQuery->where('generic_name', 'like', "%{$term}%")
                    ->orWhere('brand_name', 'like', "%{$term}%")
                    ->orWhere('medicine_code', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhereHas('batches', fn (Builder $batchQuery) => $batchQuery->where('batch_number', 'like', "%{$term}%"))
                    ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('batches.supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', "%{$term}%"));
            });
        });
    }

    public function stockStatus(): string
    {
        $stock = $this->usableStockQuantity();

        if ($stock === 0) {
            return 'out';
        }

        if ($stock <= max(1, (int) floor($this->minimum_stock_level / 2))) {
            return 'critical';
        }

        if ($stock <= $this->minimum_stock_level) {
            return 'low';
        }

        return 'normal';
    }

    public function recommendedReorderQuantity(): int
    {
        return max(0, $this->maximum_stock_level - $this->usableStockQuantity());
    }

    public function usableStockQuantity(): int
    {
        return (int) ($this->usable_stock ?? $this->batches()->availableFefo()->sum('quantity'));
    }

    public function unitsPerBox(): ?int
    {
        return $this->box_size;
    }

    public function scannableBarcodeValue(): string
    {
        $barcodeValue = trim((string) ($this->barcode ?: $this->medicine_code));

        if (! preg_match('/^\d{13}$/', $barcodeValue) || self::isValidEan13($barcodeValue)) {
            return $barcodeValue;
        }

        $barcodePrefix = substr($barcodeValue, 0, 12);

        return $barcodePrefix.self::ean13CheckDigit($barcodePrefix);
    }

    private static function isValidEan13(string $barcodeValue): bool
    {
        $barcodePrefix = substr($barcodeValue, 0, 12);

        return self::ean13CheckDigit($barcodePrefix) === (int) substr($barcodeValue, -1);
    }

    private static function ean13CheckDigit(string $barcodePrefix): int
    {
        $checksum = array_sum(array_map(
            fn (string $digit, int $index): int => (int) $digit * ($index % 2 === 0 ? 1 : 3),
            str_split($barcodePrefix),
            array_keys(str_split($barcodePrefix)),
        ));

        return (10 - $checksum % 10) % 10;
    }
}
