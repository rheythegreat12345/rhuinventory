@props(['status'])
@php
    $class = match ($status) { 'normal','active','safe','success' => 'badge-success', 'low','warning','30_days','60_days','90_days' => 'badge-warning', 'out','critical','expired','danger','7_days','inactive' => 'badge-danger', default => 'badge-info' };
    $label = match ($status) { 'out' => 'Out of stock', '7_days' => 'Within 7 days', '30_days' => 'Within 30 days', '60_days' => 'Within 60 days', '90_days' => 'Within 90 days', default => str($status)->replace('_',' ')->headline() };
@endphp
<span {{ $attributes->class(['badge', $class]) }}>{{ $label }}</span>
