<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-1 text-sm font-semibold text-blue-600">OVERVIEW</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Welcome back, {{ auth()->user()->name }}</h1>
                <p class="mt-2 text-sm text-slate-500">Here is what is happening across your tissue culture operations today.</p>
            </div>
            <div class="inline-flex items-center gap-2 self-start rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm sm:self-auto"><svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z" /></svg>{{ now()->format('d M Y') }}</div>
        </div>

        <x-pending-returns-alert :returns="$pendingReturns" />

        {{-- Row 1: top-level stat cards --}}
        <div class="mb-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as $stat)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</p>
                            <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($stat['value']) }}</p>
                        </div>
                        <span @class(['grid h-11 w-11 place-items-center rounded-xl', 'bg-blue-50 text-blue-600' => $stat['color'] === 'blue', 'bg-emerald-50 text-emerald-600' => $stat['color'] === 'emerald', 'bg-violet-50 text-violet-600' => $stat['color'] === 'violet', 'bg-amber-50 text-amber-600' => $stat['color'] === 'amber'])>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}" /></svg>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Row 2: Quick Actions + Recent Bookings --}}
        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-slate-900">Quick Actions</h2>
                    <p class="mt-1 text-sm text-slate-500">Jump directly to frequently used modules.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('dealers.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-blue-200 hover:bg-blue-50"><span class="grid h-10 w-10 place-items-center rounded-lg bg-blue-600 text-white"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16v13H4z M8 7V4h8v3 M4 12h16" /></svg></span><span><span class="block font-semibold text-slate-800">Dealers</span><span class="text-xs text-slate-500">Manage dealer network</span></span></a>
                    <a href="{{ route('farmers.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-emerald-200 hover:bg-emerald-50"><span class="grid h-10 w-10 place-items-center rounded-lg bg-emerald-600 text-white"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8z" /></svg></span><span><span class="block font-semibold text-slate-800">Farmers</span><span class="text-xs text-slate-500">Manage farmer records</span></span></a>
                    @can('create', \App\Models\Booking::class)
                        <a href="{{ route('bookings.create') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-violet-200 hover:bg-violet-50"><span class="grid h-10 w-10 place-items-center rounded-lg bg-violet-600 text-white"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg></span><span><span class="block font-semibold text-slate-800">New booking</span><span class="text-xs text-slate-500">Create a plant booking</span></span></a>
                    @endcan
                    @can('viewAny', \App\Models\Dispatch::class)
                        <a href="{{ route('dispatches.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-amber-200 hover:bg-amber-50"><span class="grid h-10 w-10 place-items-center rounded-lg bg-amber-600 text-white"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v10H3z M14 10h4l3 3v4h-7v-7z" /></svg></span><span><span class="block font-semibold text-slate-800">Dispatch</span><span class="text-xs text-slate-500">Track shipments</span></span></a>
                    @endcan
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-slate-300 hover:bg-slate-50"><span class="grid h-10 w-10 place-items-center rounded-lg bg-slate-700 text-white"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z" /></svg></span><span><span class="block font-semibold text-slate-800">Settings</span><span class="text-xs text-slate-500">Update your account</span></span></a>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Recent Bookings</h2>
                        <p class="mt-1 text-sm text-slate-500">The latest plant bookings across all dealers.</p>
                    </div>
                    @can('viewAny', \App\Models\Booking::class)
                        <a href="{{ route('bookings.index') }}" class="shrink-0 text-sm font-semibold text-blue-600 hover:text-blue-800">View all</a>
                    @endcan
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($recentBookings as $booking)
                        <div class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800">{{ $booking->booking_no }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $booking->dealer?->dealer_name }} · {{ $booking->farmer?->farmer_name }}</p>
                            </div>
                            <x-booking-status-badge :status="$booking->approval_status" class="shrink-0" />
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-slate-500">No bookings yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Row 2b: Top Rated Dealer + Farmer --}}
        <div class="mb-6 grid gap-6 sm:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-bold text-slate-900">Top Rated Dealer</h2>
                @if($topRatedDealer && $topRatedDealer->rateable)
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('dealers.show', $topRatedDealer->rateable) }}" class="truncate text-sm font-semibold text-blue-600 hover:text-blue-800">{{ $topRatedDealer->rateable->dealer_name }}</a>
                            <p class="mt-1 text-xs text-slate-500">{{ $topRatedDealer->currentScore() }} / 10 · {{ str_repeat('★', (int) $topRatedDealer->currentStars()) }}{{ str_repeat('☆', 5 - (int) $topRatedDealer->currentStars()) }}</p>
                        </div>
                        <span @class(['inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-amber-100 text-amber-700' => $topRatedDealer->is_manual_override, 'bg-blue-100 text-blue-700' => ! $topRatedDealer->is_manual_override])>{{ $topRatedDealer->is_manual_override ? '✏️ Manual' : '⭐ Auto' }}</span>
                    </div>
                @else
                    <p class="text-sm text-slate-500">No dealer ratings yet.</p>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-bold text-slate-900">Top Rated Farmer</h2>
                @if($topRatedFarmer && $topRatedFarmer->rateable)
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('farmers.show', $topRatedFarmer->rateable) }}" class="truncate text-sm font-semibold text-blue-600 hover:text-blue-800">{{ $topRatedFarmer->rateable->farmer_name }}</a>
                            <p class="mt-1 text-xs text-slate-500">{{ $topRatedFarmer->currentScore() }} / 10 · {{ str_repeat('★', (int) $topRatedFarmer->currentStars()) }}{{ str_repeat('☆', 5 - (int) $topRatedFarmer->currentStars()) }}</p>
                        </div>
                        <span @class(['inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-amber-100 text-amber-700' => $topRatedFarmer->is_manual_override, 'bg-blue-100 text-blue-700' => ! $topRatedFarmer->is_manual_override])>{{ $topRatedFarmer->is_manual_override ? '✏️ Manual' : '⭐ Auto' }}</span>
                    </div>
                @else
                    <p class="text-sm text-slate-500">No farmer ratings yet.</p>
                @endif
            </section>
        </div>

        {{-- Row 3: ERP Status + Latest Activities --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-slate-900">ERP Status</h2>
                    <p class="mt-1 text-sm text-slate-500">Arcadia ERP is operating normally.</p>
                </div>
                <dl class="divide-y divide-slate-100">
                    <div class="flex items-center justify-between py-3"><dt class="text-sm text-slate-600">Application</dt><dd class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Online</dd></div>
                    <div class="flex items-center justify-between py-3"><dt class="text-sm text-slate-600">Database</dt><dd class="text-sm font-semibold text-emerald-600">Connected</dd></div>
                    <div class="flex items-center justify-between py-3"><dt class="text-sm text-slate-600">Signed in as</dt><dd class="max-w-36 truncate text-sm font-semibold text-slate-800">{{ auth()->user()->email }}</dd></div>
                    <div class="flex items-center justify-between py-3"><dt class="text-sm text-slate-600">Version</dt><dd class="text-sm font-semibold text-slate-800">v1.0.0</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-slate-900">Latest Activities</h2>
                    <p class="mt-1 text-sm text-slate-500">Recent actions recorded across the ERP.</p>
                </div>
                <div class="space-y-4">
                    @forelse($latestActivities as $entry)
                        <div class="relative pb-4 pl-6 last:pb-0">
                            <span class="absolute left-0 top-1 grid h-4 w-4 place-items-center rounded-full bg-blue-600"><span class="h-1.5 w-1.5 rounded-full bg-white"></span></span>
                            @unless($loop->last)<span class="absolute left-[7px] top-5 h-full w-px bg-slate-200"></span>@endunless
                            <p class="text-sm font-semibold capitalize text-slate-800">{{ \Illuminate\Support\Str::headline($entry->module) }} {{ $entry->action }}{{ $entry->remarks ? ' — '.$entry->remarks : '' }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $entry->created_at->diffForHumans() }} · by {{ $entry->user?->name ?? 'System' }}</p>
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-slate-500">No activity recorded yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
