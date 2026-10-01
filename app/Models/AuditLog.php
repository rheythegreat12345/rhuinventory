<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id', 'description',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Return only the meaningful differences between the recorded before and after values.
     *
     * @return array<string, array{before: mixed, after: mixed}>
     */
    public function valueDifferences(): array
    {
        $oldValues = $this->old_values ?? [];
        $newValues = $this->new_values ?? [];
        $excludedFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'];
        $differences = [];

        foreach (array_unique([...array_keys($oldValues), ...array_keys($newValues)]) as $field) {
            if (in_array($field, $excludedFields, true)) {
                continue;
            }

            $before = $oldValues[$field] ?? null;
            $after = $newValues[$field] ?? null;

            if (json_encode($before) === json_encode($after)) {
                continue;
            }

            $differences[$field] = ['before' => $before, 'after' => $after];
        }

        return $differences;
    }

    public function formatAuditedValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—';
        }

        return (string) $value;
    }
}
