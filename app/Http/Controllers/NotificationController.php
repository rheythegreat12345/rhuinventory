<?php

namespace App\Http\Controllers;

use App\Models\InventoryNotification;
use App\Models\InventoryNotificationRead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() === 'unread' ? 'unread' : 'all';
        $notificationQuery = InventoryNotification::query()->visibleTo($request->user());
        $totalCount = (clone $notificationQuery)->count();
        $unreadCount = (clone $notificationQuery)->unreadFor($request->user())->count();
        $notifications = (clone $notificationQuery)
            ->withReadStateFor($request->user())
            ->when($status === 'unread', fn ($query) => $query->unreadFor($request->user()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', compact('notifications', 'status', 'totalCount', 'unreadCount'));
    }

    public function read(Request $request, InventoryNotification $notification): RedirectResponse
    {
        abort_unless(
            InventoryNotification::query()
                ->visibleTo($request->user())
                ->whereKey($notification->getKey())
                ->exists(),
            403,
        );
        $notification->markReadBy($request->user());

        return redirect()->to($this->destinationFor($notification));
    }

    public function readAll(Request $request): RedirectResponse
    {
        InventoryNotification::query()
            ->visibleTo($request->user())
            ->whereNotNull('user_id')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $sharedNotificationIds = InventoryNotification::query()
            ->visibleTo($request->user())
            ->whereNull('user_id')
            ->unreadFor($request->user())
            ->pluck('id');

        if ($sharedNotificationIds->isNotEmpty()) {
            $now = now();
            InventoryNotificationRead::query()->upsert(
                $sharedNotificationIds->map(fn (int $notificationId): array => [
                    'inventory_notification_id' => $notificationId,
                    'user_id' => $request->user()->id,
                    'read_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
                ['inventory_notification_id', 'user_id'],
                ['read_at', 'updated_at'],
            );
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(Request $request, InventoryNotification $notification): RedirectResponse
    {
        abort_unless(
            InventoryNotification::query()
                ->visibleTo($request->user())
                ->whereKey($notification->getKey())
                ->exists(),
            403,
        );

        $notification->deleteFor($request->user());

        return back()->with('success', 'Notification deleted from your history.');
    }

    private function destinationFor(InventoryNotification $notification): string
    {
        $destination = $notification->data['url'] ?? route('notifications.index', [], false);

        if (! is_string($destination)) {
            return route('notifications.index', [], false);
        }

        $parts = parse_url($destination);
        $path = $parts['path'] ?? null;

        if (! is_string($path) || ! str_starts_with($path, '/')) {
            return route('notifications.index', [], false);
        }

        return $path.(isset($parts['query']) ? "?{$parts['query']}" : '');
    }
}
