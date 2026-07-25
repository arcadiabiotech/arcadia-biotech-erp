@props(['status'])

@php
    $colors = [
        'draft' => 'bg-slate-100 text-slate-600',
        'generated' => 'bg-blue-100 text-blue-700',
        'partially_paid' => 'bg-amber-100 text-amber-700',
        'paid' => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-rose-100 text-rose-700',
    ];
    $labels = [
        'partially_paid' => 'Partially Paid',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold '.($colors[$status] ?? 'bg-slate-100 text-slate-600')]) }}>
    {{ $labels[$status] ?? ucfirst($status) }}
</span>
