<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ $systemSettings['system_name'] ?? 'MediStock RHU' }}</title>
    <script>document.documentElement.dataset.theme=(localStorage.getItem('theme')==='dark'||(localStorage.getItem('theme')!=='light'&&matchMedia('(prefers-color-scheme: dark)').matches))?'dark':'light';</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body data-default-theme="{{ auth()->user()->preferences['theme'] ?? ($systemSettings['default_theme'] ?? 'system') }}">
<div class="app-shell">
    <aside class="sidebar" aria-label="Primary navigation">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark"><x-icon name="medicine" /></span>
            <span class="brand-copy"><span class="brand-name">{{ $systemSettings['system_name'] ?? 'MediStock RHU' }}</span><span class="brand-subtitle">Medicine inventory</span></span>
        </a>
        <nav class="sidebar-scroll">
            <div class="nav-section">Overview</div>
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><x-icon name="home" /><span>Dashboard</span></a>
            @if(auth()->user()->hasPermission('medicines.view'))
                <a class="nav-link {{ request()->routeIs('medicines.*') ? 'active' : '' }}" href="{{ route('medicines.index') }}"><x-icon name="medicine" /><span>Medicines</span></a>
                <a class="nav-link {{ request()->routeIs('inventory.low-stock') ? 'active' : '' }}" href="{{ route('inventory.low-stock') }}"><x-icon name="alert" /><span>Low Stock</span></a>
                <a class="nav-link {{ request()->routeIs('inventory.expirations') ? 'active' : '' }}" href="{{ route('inventory.expirations') }}"><x-icon name="calendar" /><span>Expirations</span></a>
            @endif

            @if(auth()->user()->hasPermission('stock.receive') || auth()->user()->hasPermission('stock.release') || auth()->user()->hasPermission('stock.adjust'))
                <div class="nav-section">Stock operations</div>
                @if(auth()->user()->hasPermission('stock.receive'))<a class="nav-link {{ request()->routeIs('stock.in*') ? 'active' : '' }}" href="{{ route('stock.in') }}"><x-icon name="stock-in" /><span>Stock In</span></a>@endif
                @if(auth()->user()->hasPermission('stock.release'))<a class="nav-link {{ request()->routeIs('stock.out*') ? 'active' : '' }}" href="{{ route('stock.out') }}"><x-icon name="stock-out" /><span>Stock Out / Dispense</span></a>@endif
                @if(auth()->user()->hasPermission('stock.adjust'))<a class="nav-link {{ request()->routeIs('stock.adjustment*') ? 'active' : '' }}" href="{{ route('stock.adjustment') }}"><x-icon name="adjust" /><span>Adjustments</span></a>@endif
            @endif
            @if(auth()->user()->hasPermission('transactions.view'))<a class="nav-link {{ request()->routeIs('transactions.*') ? 'active' : '' }}" href="{{ route('transactions.index') }}"><x-icon name="transaction" /><span>Transactions</span></a>@endif

            @if(auth()->user()->hasPermission('categories.manage') || auth()->user()->hasPermission('suppliers.manage'))
                <div class="nav-section">Catalog</div>
                @if(auth()->user()->hasPermission('suppliers.manage'))<a class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}"><x-icon name="supplier" /><span>Suppliers</span></a>@endif
                @if(auth()->user()->hasPermission('categories.manage'))<a class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="{{ route('categories.index') }}"><x-icon name="category" /><span>Categories</span></a>@endif
            @endif

            @if(auth()->user()->hasPermission('reports.view') || auth()->user()->hasPermission('analytics.view'))
                <div class="nav-section">Insights</div>
                @if(auth()->user()->hasPermission('reports.view'))<a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><x-icon name="report" /><span>Reports</span></a>@endif
                @if(auth()->user()->hasPermission('analytics.view'))<a class="nav-link {{ request()->routeIs('analytics') ? 'active' : '' }}" href="{{ route('analytics') }}"><x-icon name="analytics" /><span>Analytics</span></a>@endif
            @endif

            @if(auth()->user()->hasPermission('users.manage') || auth()->user()->hasPermission('audit.view') || auth()->user()->hasPermission('settings.manage'))
                <div class="nav-section">Administration</div>
                @if(auth()->user()->hasPermission('users.manage'))<a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><x-icon name="users" /><span>User Management</span></a>@endif
                @if(auth()->user()->hasPermission('users.manage'))<a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}"><x-icon name="settings" /><span>Roles & Permissions</span></a>@endif
                @if(auth()->user()->hasPermission('audit.view'))<a class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}" href="{{ route('audit.index') }}"><x-icon name="audit" /><span>Audit Log</span></a>@endif
                @if(auth()->user()->hasPermission('settings.manage'))<a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.edit') }}"><x-icon name="settings" /><span>Settings</span></a>@endif
            @endif
        </nav>
        <div class="sidebar-footer">
            <a class="sidebar-user" href="{{ route('profile.edit') }}">
                <span class="avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                <span class="sidebar-user-meta"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->role?->name ?? 'No role' }}</span></span>
            </a>
        </div>
    </aside>
    <div class="mobile-overlay" data-mobile-overlay></div>

    <main class="app-main">
        <header class="topbar">
            <button class="icon-button sidebar-toggle" type="button" data-sidebar-toggle aria-label="Toggle sidebar"><x-icon name="menu" /></button>
            <span class="topbar-spacer"></span>
            <div class="topbar-actions">
                <a class="icon-button scanner-shortcut desktop-only" href="{{ route('scanner') }}" title="Barcode scanner" aria-label="Open barcode scanner"><x-icon name="scan" /></a>
                <button class="icon-button" type="button" data-theme-toggle title="Toggle theme"><x-icon name="moon" /></button>
                <div class="topbar-action notification-dropdown" data-dropdown>
                    <button class="icon-button notification-trigger" type="button" data-dropdown-trigger aria-label="Notifications, {{ $unreadNotificationCount ?? 0 }} unread" aria-haspopup="true" aria-expanded="false" aria-controls="notification-menu"><x-icon name="bell" />@if(($unreadNotificationCount ?? 0) > 0)<span class="notification-dot">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>@endif</button>
                    <div class="dropdown-menu notification-menu" id="notification-menu" data-dropdown-menu hidden>
                        <div class="notification-menu-head">
                            <div><strong>Notifications</strong><span>{{ $unreadNotificationCount ?? 0 }} unread</span></div>
                            @if(($unreadNotificationCount ?? 0) > 0)
                                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="notification-read-all" type="submit"><x-icon name="check" class="icon-sm" /> Mark all read</button></form>
                            @endif
                        </div>
                        <div class="notification-menu-list">
                            @forelse(($layoutNotifications ?? collect()) as $notification)
                                @php($notificationIcon = match ($notification->level) { 'danger', 'warning' => 'alert', 'success' => 'check', default => 'bell' })
                                <form class="notification-menu-form" method="POST" action="{{ route('notifications.read', $notification) }}">
                                    @csrf
                                    <button class="notification-menu-item {{ $notification->read_at ? '' : 'unread' }}" type="submit">
                                        <span class="notification-icon {{ $notification->level }}"><x-icon name="{{ $notificationIcon }}" class="icon-sm" /></span>
                                        <span class="notification-copy"><strong>{{ $notification->title }}</strong><p>{{ $notification->message }}</p><span class="notification-meta"><time title="{{ $notification->created_at->format('M d, Y g:i A') }}">{{ $notification->created_at->diffForHumans() }}</time>@if(!$notification->read_at)<span class="notification-unread-mark">Unread</span>@endif</span></span>
                                    </button>
                                </form>
                            @empty
                                <div class="notification-empty"><span class="notification-icon"><x-icon name="check" class="icon-sm" /></span><strong>You’re all caught up</strong><p>New inventory alerts will appear here.</p></div>
                            @endforelse
                        </div>
                        <a class="notification-menu-footer" href="{{ route('notifications.index') }}">View notification center <x-icon name="chevron" class="icon-sm" /></a>
                    </div>
                </div>
                <div class="topbar-action" data-dropdown>
                <button class="avatar" type="button" data-dropdown-trigger style="border:0;cursor:pointer">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</button>
                <div class="dropdown-menu" data-dropdown-menu hidden style="width:220px">
                    <a class="notification-item" href="{{ route('profile.edit') }}"><span class="notification-icon"><x-icon name="profile" class="icon-sm" /></span><span class="notification-copy"><strong>My profile</strong><p>Account and password</p></span></a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="notification-item" type="submit" style="width:100%;border:0;background:none;color:var(--danger);cursor:pointer"><x-icon name="logout" /><strong>Sign out</strong></button></form>
                </div>
                </div>
            </div>
        </header>

        <div class="page">
            @if(session('success'))<div class="alert alert-success" data-dismissible><x-icon name="check" /><span>{{ session('success') }}</span><button data-dismiss type="button"><x-icon name="x" class="icon-sm" /></button></div>@endif
            @if(session('error'))<div class="alert alert-error" data-dismissible><x-icon name="alert" /><span>{{ session('error') }}</span><button data-dismiss type="button"><x-icon name="x" class="icon-sm" /></button></div>@endif
            @if($errors->any())<div class="alert alert-error" data-dismissible><x-icon name="alert" /><span><strong>Please check the form.</strong><br>{{ $errors->first() }}</span><button data-dismiss type="button"><x-icon name="x" class="icon-sm" /></button></div>@endif
            @yield('content')
        </div>
    </main>
</div>

<dialog id="confirm-dialog">
    <div class="dialog-body"><span class="dialog-icon"><x-icon name="alert" /></span><h3>Confirm this action</h3><p data-confirm-message>This action cannot be undone.</p></div>
    <div class="dialog-actions"><button class="btn btn-secondary" type="button" data-confirm-cancel>Cancel</button><button class="btn btn-danger" type="button" data-confirm-submit>Confirm</button></div>
</dialog>
@stack('scripts')
</body>
</html>
