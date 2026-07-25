<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="mb-8">
            <a href="{{ route('dispatches.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to dispatches</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">New dispatch</h1>
            <p class="mt-2 text-sm text-slate-500">Pick a vehicle whose loading has been approved in Dispatch Planning. A vehicle stays listed until every planned booking on it has been fully dispatched.</p>
        </div>

        @if ($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="grid gap-4">
            @forelse($assignments as $assignment)
                @php
                    $dealers = $assignment->items->pluck('booking.dealer')->filter()->unique('id');
                    $remainingPlants = $assignment->items->sum('remaining_qty');
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $assignment->dispatchPlan->plan_no }}</p>
                            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $assignment->vehicle->vehicle_no }}</h2>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $assignment->driver_name ?? 'No driver set' }}@if($assignment->driver_mobile) · {{ $assignment->driver_mobile }} @endif</p>
                        </div>
                        <a href="{{ route('dispatches.create', ['vehicle_assignment_id' => $assignment->id]) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Create dispatch →</a>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-3 text-center text-xs">
                        <div><p class="font-bold text-slate-800">{{ $dealers->count() }}</p><p class="text-slate-500">Dealer{{ $dealers->count() === 1 ? '' : 's' }}</p></div>
                        <div><p class="font-bold text-slate-800">{{ $assignment->items->count() }}</p><p class="text-slate-500">Booking{{ $assignment->items->count() === 1 ? '' : 's' }}</p></div>
                        <div><p class="font-bold text-slate-800">{{ number_format($remainingPlants) }}</p><p class="text-slate-500">Plants remaining</p></div>
                    </div>

                    <div class="mt-4 divide-y divide-slate-100 text-sm">
                        @foreach($assignment->items as $item)
                            @if($item->remaining_qty > 0)
                                <div class="flex items-center justify-between py-2">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-slate-800">{{ $item->booking->booking_no }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $item->booking->dealer?->dealer_name }} / {{ $item->booking->farmer?->farmer_name }}</p>
                                    </div>
                                    <div class="shrink-0 text-right text-xs">
                                        <p class="font-semibold text-slate-700">{{ number_format($item->remaining_qty) }} remaining</p>
                                        @if($item->dispatched_qty > 0)<p class="text-slate-400">{{ number_format($item->dispatched_qty) }} already dispatched</p>@endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
                    <p class="text-lg font-semibold text-slate-700">No vehicles available for dispatch</p>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Approve loading for a Dispatch Plan vehicle first — it will show up here once every booking on it is marked loaded and Supervisor approves.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
