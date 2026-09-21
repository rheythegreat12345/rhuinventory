<?php

namespace App\Models;

use Database\Factories\InventoryNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function reads(): HasMany
    {
        return $this->hasMany(InventoryNotificationRead::class);
    }

    /**
     * Eager-load the current user's read receipt for shared notifications.
     *
     * @param  Builder<InventoryNotification>  $query
     * @return Builder<InventoryNotification>
     */
    public function scopeWithReadStateFor(Builder $query, User $user): Builder
    {
        return $query->with(['reads' => fn (HasMany $readQuery) => $readQuery->where('user_id', $user->id)]);
    }

    /**
     * Limit results to notifications unread by the current user.
     *
     * @param  Builder<InventoryNotification>  $query
     * @return Builder<InventoryNotification>
     */
    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $unreadQuery) use ($user): void {
            $unreadQuery
                ->where(fn (Builder $personalQuery) => $personalQuery->whereNotNull('user_id')->whereNull('read_at'))
                ->orWhere(fn (Builder $sharedQuery) => $sharedQuery->whereNull('user_id')->whereDoesntHave('reads', fn (Builder $readQuery) => $readQuery->where('user_id', $user->id)));
        });
    }

    public function isReadBy(User $user): bool
    {
        if ($this->user_id !== null) {
            return $this->read_at !== null;
        }

        return $this->relationLoaded('reads')
            ? $this->reads->isNotEmpty()
            : $this->reads()->where('user_id', $user->id)->exists();
    }

    public function markReadBy(User $user): void
    {
        if ($this->user_id !== null) {
            $this->update(['read_at' => now()]);

            return;
        }

        $this->reads()->updateOrCreate(
            ['user_id' => $user->id],
            ['read_at' => now(), 'deleted_at' => null],
        );
    }

    public function deleteFor(User $user): void
    {
        $now = now();

        $this->reads()->updateOrCreate(
            ['user_id' => $user->id],
            ['read_at' => $now, 'deleted_at' => $now],
        );
    }

    /**
     * Limit notifications to records that the given user is allowed to see.
     *
     * @param  Builder<InventoryNotification>  $query
     * @return Builder<InventoryNotification>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->where(fn (Builder $visibleQuery) => $visibleQuery->whereNull('user_id')->orWhere('user_id', $user->id))
            ->whereDoesntHave('reads', fn (Builder $readQuery) => $readQuery->where('user_id', $user->id)->whereNotNull('deleted_at'))
            ->when(
                $user->role?->slug !== 'administrator',
                fn (Builder $staffQuery): Builder => $staffQuery->where('type', '!=', 'account_approval_requested'),
            );
    }
}
