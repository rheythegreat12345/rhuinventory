@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a><x-icon name="chevron" class="icon-sm" /> Notifications</div>
        <h1 class="page-title">Notification center</h1>
        <p class="page-subtitle">Inventory alerts, expiring batches, stock receipts, and important system activity.</p>
    </div>
    @if($unreadCount > 0)
        <div class="page-actions">
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn btn-secondary" type="submit"><x-icon name="check" class="icon-sm" /> Mark all read</button>
            </form>
        </div>
    @endif
</div>

<div class="notification-summary" aria-label="Notification summary">
    <div class="card notification-summary-card">
        <span class="notification-summary-icon"><x-icon name="bell" /></span>
        <span><strong>{{ number_format($totalCount) }}</strong><small>Total notifications</small></span>
    </div>
    <div class="card notification-summary-card unread">
        <span class="notification-summary-icon"><x-icon name="info" /></span>
        <span><strong>{{ number_format($unreadCount) }}</strong><small>Unread notifications</small></span>
    </div>
</div>

<section class="card notification-center">
    <div class="notification-center-head">
        <div>
            <h2 class="card-title">Notification history</h2>
            <p class="card-subtitle">Select a notification to mark it as read and open its related record. Delete removes it from your history.</p>
        </div>
        <nav class="notification-filters" aria-label="Notification filters">
            <a class="{{ $status === 'all' ? 'active' : '' }}" href="{{ route('notifications.index') }}">All <span>{{ $totalCount }}</span></a>
            <a class="{{ $status === 'unread' ? 'active' : '' }}" href="{{ route('notifications.index', ['status' => 'unread']) }}">Unread <span>{{ $unreadCount }}</span></a>
        </nav>
    </div>

    <div class="notification-center-list">
        @forelse($notifications as $notification)
            @php
                $notificationIcon = match ($notification->level) {
                    'danger', 'warning' => 'alert',
                    'success' => 'check',
                    default => 'bell',
                };
                $notificationType = str($notification->type)->replace('_', ' ')->headline();
                $isRead = $notification->isReadBy(auth()->user());
            @endphp
            <div class="notification-center-row">
                <form class="notification-center-read-form" method="POST" action="{{ route('notifications.read', $notification) }}">
                    @csrf
                    <button class="notification-center-item {{ $isRead ? 'read' : 'unread' }}" type="submit">
                        <span class="notification-icon {{ $notification->level }}"><x-icon name="{{ $notificationIcon }}" /></span>
                        <span class="notification-center-copy">
                            <span class="notification-center-title"><strong>{{ $notification->title }}</strong><span class="notification-type">{{ $notificationType }}</span></span>
                            <span class="notification-message">{{ $notification->message }}</span>
                            <span class="notification-center-meta"><time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('M d, Y · g:i A') }}</time><span>{{ $notification->created_at->diffForHumans() }}</span></span>
                        </span>
                        <span class="notification-state {{ $isRead ? 'read' : 'unread' }}">{{ $isRead ? 'Read' : 'Unread' }}</span>
                        <x-icon name="chevron" class="notification-chevron" />
                    </button>
                </form>
                <form class="notification-center-delete-form" method="POST" action="{{ route('notifications.destroy', $notification) }}" data-confirm="Delete this notification from your history? This only removes it for you.">
                    @csrf
                    @method('DELETE')
                    <button class="notification-delete-button" type="submit" aria-label="Delete notification" title="Delete notification"><x-icon name="trash" class="icon-sm" /></button>
                </form>
            </div>
        @empty
            <div class="notification-center-empty">
                <span class="empty-icon"><x-icon name="check" /></span>
                <h3>{{ $status === 'unread' ? 'No unread notifications' : 'No notifications yet' }}</h3>
                <p>{{ $status === 'unread' ? 'You have reviewed every notification.' : 'New inventory alerts will appear here.' }}</p>
            </div>
        @endforelse
    </div>

    <x-pagination :paginator="$notifications" />
</section>
@endsection
