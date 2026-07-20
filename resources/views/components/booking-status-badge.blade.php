@props(['status'])

@php
    $colors = [
        'draft' => 'bg-slate-100 text-slate-600',
        'pending' => 'bg-amber-100 text-amber-700',
        'approved' => 'bg-emerald-100 text-emerald-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'rejected' => 'bg-rose-100 text-rose-700',
        'hold' => 'bg-orange-100 text-orange-700',
        'partial' => 'bg-blue-100 text-blue-700',
        'generated' => 'bg-blue-100 text-blue-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold '.($colors[$status] ?? 'bg-slate-100 text-slate-600')]) }}>
    {{ ucfirst($status) }}
</span>
