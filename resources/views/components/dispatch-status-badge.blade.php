@props(['status'])

@php
    $colors = [
        'draft' => 'bg-slate-100 text-slate-600',
        'pending' => 'bg-amber-100 text-amber-700',
        'loading' => 'bg-blue-100 text-blue-700',
        'dispatched' => 'bg-indigo-100 text-indigo-700',
        'delivered' => 'bg-teal-100 text-teal-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-rose-100 text-rose-700',
    ];

    // Internal enum value stays "loading" (DispatchService/DispatchPolicy
    // transitions key off it) — only the user-facing label changes to match
    // the "Vehicle Loaded" step name.
    $labels = [
        'loading' => 'Vehicle Loaded',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold '.($colors[$status] ?? 'bg-slate-100 text-slate-600')]) }}>
    {{ $labels[$status] ?? ucfirst($status) }}
</span>
