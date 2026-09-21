<?php

namespace App\Services;

use App\Models\InventoryNotification;
use App\Models\User;

class AccountApprovalNotificationService
{
    public function notifyAdministrators(User $pendingUser): void
    {
        User::query()
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('slug', 'administrator'))
            ->each(function (User $administrator) use ($pendingUser): void {
                InventoryNotification::query()->create([
                    'user_id' => $administrator->id,
                    'type' => 'account_approval_requested',
                    'title' => 'New account approval request',
                    'message' => "{$pendingUser->name} requested access to the inventory system.",
                    'level' => 'info',
                    'data' => [
                        'user_id' => $pendingUser->id,
                        'url' => route('admin.accounts.index'),
                    ],
                ]);
            });
    }
}
