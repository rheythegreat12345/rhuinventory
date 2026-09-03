@props(['name', 'class' => 'icon'])
@php
    $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-7h6v7"/>',
        'medicine' => '<path d="M10.5 20.5 3.5 13.5a5 5 0 0 1 7-7l7 7a5 5 0 0 1-7 7Z"/><path d="m7 17 10-10"/>',
        'boxes' => '<path d="m7.5 4.3 4.5 2.6 4.5-2.6L12 1.7 7.5 4.3Z"/><path d="M3 9.2 7.5 12l4.5-2.8L7.5 6.5 3 9.2Z"/><path d="m12 9.2 4.5 2.8L21 9.2l-4.5-2.7L12 9.2Z"/><path d="M3 9.2v5.2L7.5 17l4.5-2.6V9.2M12 9.2v5.2l4.5 2.6 4.5-2.6V9.2M7.5 17v5.2l4.5-2.6 4.5 2.6V17"/>',
        'stock-in' => '<path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M5 15v5h14v-5"/>',
        'stock-out' => '<path d="M12 16V4m0 0 4 4m-4-4L8 8"/><path d="M5 15v5h14v-5"/>',
        'adjust' => '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2"/><circle cx="16" cy="12" r="2"/><circle cx="10" cy="18" r="2"/>',
        'transaction' => '<path d="M7 7h11l-3-3m3 3-3 3M17 17H6l3 3m-3-3 3-3"/>',
        'supplier' => '<path d="M3 20h18M5 20V8l7-4 7 4v12M9 20v-5h6v5"/>',
        'category' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'report' => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h6M9 9h1"/>',
        'analytics' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'audit' => '<path d="M12 22a10 10 0 1 0-10-10"/><path d="M2 5v7h7M12 6v6l4 2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19 15a2 2 0 0 0 .4 2l-2.8 2.8a2 2 0 0 0-2-.4A2 2 0 0 0 14 21h-4a2 2 0 0 0-.6-1.6 2 2 0 0 0-2 .4L4.6 17a2 2 0 0 0 .4-2A2 2 0 0 0 3 14v-4a2 2 0 0 0 2-1 2 2 0 0 0-.4-2l2.8-2.8a2 2 0 0 0 2 .4A2 2 0 0 0 10 3h4a2 2 0 0 0 .6 1.6 2 2 0 0 0 2-.4L19.4 7a2 2 0 0 0-.4 2 2 2 0 0 0 2 1v4a2 2 0 0 0-2 1Z"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>', 'download' => '<path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/>', 'upload' => '<path d="M12 16V4m0 0 4 4m-4-4L8 8M5 20h14"/>',
        'eye' => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>',
        'edit' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>', 'trash' => '<path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15M10 10v7M14 10v7"/>',
        'moon' => '<path d="M21 12.7A9 9 0 1 1 11.3 3 7 7 0 0 0 21 12.7Z"/>', 'logout' => '<path d="M10 17l5-5-5-5M15 12H3M21 3v18h-6"/>', 'chevron' => '<path d="m9 18 6-6-6-6"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>', 'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>', 'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.6 1.9Z"/>', 'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V4h8v3M3 12h18M10 12v2h4v-2"/>',
        'alert' => '<path d="M10.3 3.7 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>', 'money' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M16 12h.01M6 9v6M18 9v6"/>',
        'scan' => '<path d="M3 8V4h4M17 4h4v4M21 16v4h-4M7 20H3v-4M7 9v6M10 9v6M14 9v6M17 9v6"/>', 'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>', 'x' => '<path d="m6 6 12 12M18 6 6 18"/>', 'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 11v6M12 7h.01"/>',
    ];
@endphp
<svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>{!! $paths[$name] ?? $paths['info'] !!}</svg>
