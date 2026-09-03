<?php

namespace App\Http\Controllers;

use App\Models\InventoryNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() === 'unread' ? 'unread' : 'all';
        $notificationQuery = InventoryNotification::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id));
        $totalCount = (clone $notificationQuery)->count();
        $unreadCount = (clone $notificationQuery)->whereNull('read_at')->count();
        $notifications = (clone $notificationQuery)
            ->when($status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', compact('notifications', 'status', 'totalCount', 'unreadCount'));
    }

    public function read(Request $request, InventoryNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === null || $notification->user_id === $request->user()->id, 403);
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return redirect()->to($notification->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        InventoryNotification::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
