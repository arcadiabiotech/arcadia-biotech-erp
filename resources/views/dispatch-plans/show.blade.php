<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('dispatch-plans.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to dispatch plans</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $plan->plan_no }}</h1>
                @php
                    $totalPlantQuantity = $plan->farmerEstimates->sum('plant_quantity');
                    $planSubtitle = collect([
                        $plan->plan_date->format('d M Y'),
                        $plan->route,
                        $totalPlantQuantity ? number_format($totalPlantQuantity).' plants (est.)' : null,
                    ])->filter()->implode(' · ');
                @endphp
                <p class="mt-2 text-sm text-slate-500">{{ $planSubtitle }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span @class([
                    'inline-flex rounded-full px-3 py-1.5 text-xs font-semibold',
                    'bg-blue-100 text-blue-700' => $plan->approval_status === 'pending',
                    'bg-emerald-100 text-emerald-700' => $plan->approval_status === 'approved',
                    'bg-rose-100 text-rose-700' => $plan->approval_status === 'rejected',
                ])>{{ ucfirst($plan->approval_status) }}</span>
                @can('update', $plan)<a href="{{ route('dispatch-plans.edit', $plan) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Edit plan</a>@endcan
            </div>
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        @if($plan->approval_status === 'rejected' && $plan->rejection_reason)
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"><span class="font-semibold">Rejected:</span> {{ $plan->rejection_reason }}</div>
        @endif

        @if($plan->approval_status === 'pending')
            <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">This plan is awaiting approval on the <a href="{{ route('dispatches.index') }}" class="font-semibold underline">Dispatch page</a> — vehicles cannot be added until it's approved there.</div>
        @endif

        @if($plan->approval_status === 'approved')
            <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">Add a vehicle to this plan from the <a href="{{ route('dispatches.index') }}" class="font-semibold underline">Dispatch page</a>.</div>
        @endif

        @if($plan->remarks)
            <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700">{{ $plan->remarks }}</div>
        @endif

        @if($plan->farmerEstimates->isNotEmpty())
            <div class="mb-8 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Dealers &amp; bookings</h2>
                    <span class="text-sm font-semibold text-slate-700">Total: {{ number_format($totalPlantQuantity) }} plants</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($plan->farmerEstimates->groupBy('dealer_id') as $dealerGroup)
                        <div class="px-6 py-4">
                            <p class="text-sm font-semibold text-slate-800">{{ $dealerGroup->first()->dealer?->dealer_name ?? '—' }}
                                <span class="font-normal text-slate-500">· {{ number_format($dealerGroup->sum('plant_quantity')) }} plants</span>
                            </p>
                            <dl class="mt-2 space-y-1">
                                @foreach($dealerGroup as $estimate)
                                    <div class="flex flex-wrap items-center justify-between gap-x-3 text-xs text-slate-600">
                                        <dt>
                                            {{ $estimate->booking?->booking_no ?? '—' }} —
                                            {{ $estimate->farmer?->farmer_name ?? '—' }}
                                            @if($estimate->booking?->variety) · {{ $estimate->booking->variety }} @endif
                                            @if($estimate->dispatch_type)
                                                <span @class([
                                                    'ml-1 inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                                                    'bg-emerald-100 text-emerald-700' => $estimate->dispatch_type === 'full',
                                                    'bg-amber-100 text-amber-700' => $estimate->dispatch_type === 'partial',
                                                ])>{{ ucfirst($estimate->dispatch_type) }}</span>
                                            @endif
                                        </dt>
                                        <dd class="font-medium">{{ number_format($estimate->plant_quantity) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            @forelse($plan->vehicleAssignments as $assignment)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">{{ $assignment->vehicle->vehicle_no }}</h2>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $assignment->driver_name ?? 'No driver set' }}@if($assignment->helper_name) · Helper: {{ $assignment->helper_name }} @endif</p>
                            @if($assignment->marketingOfficer)<p class="text-xs text-slate-500">Marketing: {{ $assignment->marketingOfficer->name }}</p>@endif
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <span @class([
                                'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold',
                                'bg-blue-100 text-blue-700' => $assignment->loading_status === 'pending',
                                'bg-amber-100 text-amber-700' => $assignment->loading_status === 'loading',
                                'bg-emerald-100 text-emerald-700' => $assignment->loading_status === 'completed',
                            ])>{{ ucfirst($assignment->loading_status) }}</span>
                            @if($assignment->dispatch_status)
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold',
                                    'bg-slate-100 text-slate-600' => $assignment->dispatch_status === 'available',
                                    'bg-amber-100 text-amber-700' => $assignment->dispatch_status === 'partially_dispatched',
                                    'bg-emerald-100 text-emerald-700' => $assignment->dispatch_status === 'completed',
                                ])>{{ $assignment->dispatch_status === 'partially_dispatched' ? 'Partially dispatched' : ucfirst($assignment->dispatch_status) }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @forelse($assignment->items as $item)
                            @php
                                $dealer = $item->booking->dealer;
                                $dealerLocation = $dealer ? collect([$dealer->village?->name, $dealer->taluka?->name, $dealer->district?->name, $dealer->state?->name])->filter()->implode(', ') : null;
                            @endphp
                            <div class="flex items-center justify-between gap-2 py-2 text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-800">{{ $item->booking->booking_no }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $dealer?->dealer_name }} / {{ $item->booking->farmer?->farmer_name }} · {{ number_format($item->dispatch_qty) }} plants</p>
                                    @if($dealerLocation)<p class="truncate text-xs text-slate-400">📍 {{ $dealerLocation }}</p>@endif
                                    @if($item->dispatched_qty > 0)
                                        <p class="truncate text-xs text-slate-500">{{ number_format($item->dispatched_qty) }} of {{ number_format($item->dispatch_qty) }} dispatched
                                            @foreach($item->dispatchLines->pluck('dispatch')->filter()->unique('id') as $lineDispatch)
                                                · <a href="{{ route('dispatches.show', $lineDispatch) }}" class="font-semibold text-blue-600 hover:underline">{{ $lineDispatch->dispatch_no }}</a>
                                            @endforeach
                                        </p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    @can('update', $assignment)
                                        <form method="POST" action="{{ route('dispatch-plan-items.destroy', $item) }}" onsubmit="return confirm('Remove this booking from the vehicle?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-rose-600 hover:text-rose-800">Remove</button></form>
                                    @endcan
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-xs text-slate-400">No bookings assigned yet.</p>
                        @endforelse
                    </div>

                    <div class="mt-4 flex justify-end gap-3 border-t border-slate-100 pt-4">
                        @can('delete', $assignment)
                            <form method="POST" action="{{ route('vehicle-assignments.destroy', $assignment) }}" onsubmit="return confirm('Remove this vehicle from the plan?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-rose-600 hover:text-rose-800">Remove vehicle</button></form>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center lg:col-span-2">
                    <p class="text-lg font-semibold text-slate-700">No vehicles added yet</p>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Add a vehicle to this plan from the <a href="{{ route('dispatches.index') }}" class="font-semibold text-blue-600 hover:underline">Dispatch page</a> to start grouping bookings.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
