@props(['type' => 'success'])

@php
    $classes = match ($type) {
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300',
        'error' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300',
        default => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300',
    };
@endphp

<div {{ $attributes->class("rounded-xl border px-4 py-3 text-sm font-medium $classes") }}>{{ $slot }}</div>
