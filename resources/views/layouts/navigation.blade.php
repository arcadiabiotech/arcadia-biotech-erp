@php
    $items = [
        ['Dashboard', route('dashboard'), 'dashboard', 'M3 12l9-9 9 9v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9z M9 22v-6h6v6'],
        ['Dealers', route('dealers.index'), 'dealers.*', 'M4 7h16v13H4z M8 7V4h8v3 M4 12h16'],
        ['Farmers', route('customers.index'), 'customers.*', 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8z M22 21v-2a4 4 0 00-3-3.87 M16 3.13a4 4 0 010 7.75'],
        ['States', route('states.index'), 'states.*', 'M12 22a10 10 0 100-20 10 10 0 000 20z M2 12h20 M12 2a15.3 15.3 0 010 20 15.3 15.3 0 010-20'],
        ['Districts', route('districts.index'), 'districts.*', 'M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3V6z M9 3v15 M15 6v15'],
        ['Talukas', route('talukas.index'), 'talukas.*', 'M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 1118 0z M12 10a3 3 0 100-6 3 3 0 000 6z'],
        ['Villages', url('/villages'), 'villages.*', 'M3 21h18 M5 21V7l7-4 7 4v14 M9 21v-5h6v5 M9 9h.01 M15 9h.01'],
        ['Booking', url('/bookings'), 'bookings.*', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2 M9 5a3 3 0 006 0 M9 5a3 3 0 016 0 M9 12h6 M9 16h4'],
        ['Booking Approval', url('/booking-approvals'), 'booking-approvals.*', 'M9 12l2 2 4-4 M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['Dispatch', url('/dispatches'), 'dispatches.*', 'M3 7h11v10H3z M14 10h4l3 3v4h-7v-7z M7 21a2 2 0 100-4 2 2 0 000 4z M18 21a2 2 0 100-4 2 2 0 000 4z'],
        ['Challan', url('/challans'), 'challans.*', 'M6 2h9l4 4v16H6z M14 2v5h5 M9 13h6 M9 17h6'],
        ['Invoice', url('/invoices'), 'invoices.*', 'M6 2h12v20l-3-2-3 2-3-2-3 2V2z M9 8h6 M9 12h6 M9 16h4'],
        ['Payment', url('/payments'), 'payments.*', 'M3 5h18a2 2 0 012 2v10a2 2 0 01-2 2H3a2 2 0 01-2-2V7a2 2 0 012-2z M1 10h22'],
        ['Stock', url('/stock'), 'stock.*', 'M21 16V8l-9-5-9 5v8l9 5 9-5z M3.3 7.8L12 13l8.7-5.2 M12 13v8'],
        ['Reports', url('/reports'), 'reports.*', 'M4 20V10 M10 20V4 M16 20v-7 M22 20H2'],
        ['Users', url('/users'), 'users.*', 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8z M19 8v6 M22 11h-6'],
        ['Settings', route('profile.edit'), 'profile.*', 'M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z M19.4 15a1.7 1.7 0 00.34 1.88l.06.06-2 2-.06-.06a1.7 1.7 0 00-1.88-.34 1.7 1.7 0 00-1.04 1.56V20h-2.8v-.1A1.7 1.7 0 0011 18.34a1.7 1.7 0 00-1.88.34l-.06.06-2-2 .06-.06A1.7 1.7 0 007.46 15a1.7 1.7 0 00-1.56-1H5.8v-2.8h.1A1.7 1.7 0 007.46 10a1.7 1.7 0 00-.34-1.88l-.06-.06 2-2 .06.06A1.7 1.7 0 0011 6.46 1.7 1.7 0 0012 4.9V4h2.8v.1A1.7 1.7 0 0015.84 6a1.7 1.7 0 001.88-.34l.06-.06 2 2-.06.06A1.7 1.7 0 0019.4 10a1.7 1.7 0 001.56 1h.1V14h-.1A1.7 1.7 0 0019.4 15z'],
    ];
@endphp

<aside class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-[#0F172A] text-slate-100 shadow-2xl">
    <div class="flex h-20 shrink-0 items-center border-b border-slate-700/70 px-5">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-500 text-lg font-black text-slate-950">A</span>
            <span><span class="block text-sm font-bold tracking-wide text-white">Arcadia Biotech</span><span class="block text-[10px] font-semibold uppercase tracking-[.18em] text-emerald-400">ERP System</span></span>
        </a>
    </div>

    <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-5" aria-label="Primary navigation">
        <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[.16em] text-slate-500">Main Menu</p>
        <div class="space-y-1">
            @foreach ($items as [$label, $href, $route, $path])
                @php($active = request()->routeIs($route))
                <a href="{{ $href }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition', 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20' => $active, 'text-slate-300 hover:bg-slate-800 hover:text-white' => ! $active])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" /></svg>
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </nav>

    <div class="shrink-0 border-t border-slate-700/70 p-4">
        <a href="{{ route('profile.edit') }}" class="mb-3 flex items-center gap-3 rounded-xl p-2 hover:bg-slate-800">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-700 text-sm font-bold text-emerald-300">{{ strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
            <span class="min-w-0"><span class="block truncate text-sm font-semibold text-white">{{ auth()->user()->name ?? 'User' }}</span><span class="block truncate text-xs text-slate-400">{{ auth()->user()->email ?? '' }}</span></span>
        </a>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-700 px-3 py-2.5 text-sm font-semibold text-slate-300 transition hover:border-rose-500/50 hover:bg-rose-500/10 hover:text-rose-300">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 17l5-5-5-5 M21 12H9 M12 19v2H5a2 2 0 01-2-2V5a2 2 0 012-2h7v2" /></svg>Logout
            </button>
        </form>
    </div>
</aside>
