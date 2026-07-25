<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8">
            <p class="mb-1 text-sm font-semibold text-blue-600">MARKETING</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Welcome back, {{ auth()->user()->name }}</h1>
            <p class="mt-2 text-sm text-slate-500">Your assigned dealers and their bookings at a glance.</p>
        </div>

        <div class="mb-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as $stat)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
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

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Recent Bookings</h2>
                        <p class="mt-1 text-sm text-slate-500">From your assigned dealers.</p>
                    </div>
                    <a href="{{ route('bookings.index') }}" class="shrink-0 text-sm font-semibold text-blue-600 hover:text-blue-800">View all</a>
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
                        <p class="py-8 text-center text-sm text-slate-500">No bookings yet for your assigned dealers.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Dispatch Status</h2>
                        <p class="mt-1 text-sm text-slate-500">Latest shipments for your assigned dealers.</p>
                    </div>
                    <a href="{{ route('dispatches.index') }}" class="shrink-0 text-sm font-semibold text-blue-600 hover:text-blue-800">View all</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($recentDispatches as $dispatch)
                        <div class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800">{{ $dispatch->dispatch_no }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $dispatch->lines->pluck('dealer.dealer_name')->filter()->unique()->implode(', ') }}</p>
                            </div>
                            <x-dispatch-status-badge :status="$dispatch->status" class="shrink-0" />
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-slate-500">No dispatches yet for your assigned dealers.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
