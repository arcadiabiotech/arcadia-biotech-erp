<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">LOGISTICS</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Dispatches</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $dispatches->total() }} dispatch{{ $dispatches->total() === 1 ? '' : 'es' }} on record.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if(auth()->user()->hasRole(['super-admin', 'admin', 'dispatch']))
                    <a href="{{ route('reports.crate-returns') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V9m3 8V5m3 12v-4M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>Crate Return Report</a>
                @endif
                <a href="{{ route('dispatches.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16" /></svg>Export Excel</a>
                @can('create', \App\Models\Dispatch::class)
                    <a href="{{ route('dispatches.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 4v16m8-8H4" /></svg>New dispatch</a>
                @endcan
            </div>
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif
        @error('status')<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $message }}</div>@enderror

        @can('viewAny', \App\Models\DispatchPlan::class)
            <div class="mb-8 grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4"><h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Dispatch plans awaiting approval</h2></div>
                    <div class="divide-y divide-slate-100">
                        @forelse($pendingPlans as $plan)
                            <div class="p-4" x-data="{ rejecting: false }">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('dispatch-plans.show', $plan) }}" class="truncate text-sm font-semibold text-slate-800 hover:text-blue-600">{{ $plan->plan_no }}</a>
                                        <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-500">
                                            <div><dt class="inline font-semibold text-slate-600">Date:</dt> <dd class="inline">{{ $plan->plan_date->format('d M Y') }}</dd></div>
                                            <div><dt class="inline font-semibold text-slate-600">Route:</dt> <dd class="inline">{{ $plan->route ?? '—' }}</dd></div>
                                            <div><dt class="inline font-semibold text-slate-600">Dealers:</dt> <dd class="inline">{{ $plan->farmerEstimates->pluck('dealer.dealer_name')->filter()->unique()->implode(', ') ?: '—' }}</dd></div>
                                            <div><dt class="inline font-semibold text-slate-600">Plants (est.):</dt> <dd class="inline">{{ $plan->farmerEstimates->sum('plant_quantity') ? number_format($plan->farmerEstimates->sum('plant_quantity')) : '—' }}</dd></div>
                                            <div class="col-span-2"><dt class="inline font-semibold text-slate-600">Created by:</dt> <dd class="inline">{{ $plan->createdBy?->name ?? '—' }} · {{ $plan->created_at->format('d M Y, h:i A') }}</dd></div>
                                            @if($plan->remarks)<div class="col-span-2"><dt class="inline font-semibold text-slate-600">Remarks:</dt> <dd class="inline">{{ $plan->remarks }}</dd></div>@endif
                                        </dl>
                                    </div>
                                    @can('approve', $plan)
                                        <div class="flex shrink-0 items-center gap-2">
                                            <form method="POST" action="{{ route('dispatch-plans.approve', $plan) }}" onsubmit="return confirm('Approve this dispatch plan? Vehicles can be added once approved.')">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button></form>
                                            <button type="button" @click="rejecting = ! rejecting" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                                        </div>
                                    @endcan
                                </div>
                                @can('reject', $plan)
                                    <div x-show="rejecting" x-cloak class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3">
                                        <form method="POST" action="{{ route('dispatch-plans.reject', $plan) }}" class="flex flex-wrap items-end gap-2">
                                            @csrf
                                            <div class="flex-1"><label class="text-xs font-semibold text-slate-600">Reason for rejection</label><input type="text" name="rejection_reason" required minlength="5" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-rose-500 focus:ring-rose-500"></div>
                                            <button class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Confirm reject</button>
                                        </form>
                                    </div>
                                @endcan

                                @if($plan->farmerEstimates->isNotEmpty())
                                    <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Dealers &amp; bookings</p>
                                        <div class="space-y-3">
                                            @foreach($plan->farmerEstimates->groupBy('dealer_id') as $dealerGroup)
                                                <div>
                                                    <p class="text-xs font-semibold text-slate-800">{{ $dealerGroup->first()->dealer?->dealer_name ?? '—' }}
                                                        <span class="font-normal text-slate-500">· {{ number_format($dealerGroup->sum('plant_quantity')) }} plants</span>
                                                    </p>
                                                    <dl class="mt-1 space-y-1">
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
                            </div>
                        @empty
                            <p class="px-6 py-8 text-center text-sm text-slate-500">No plans awaiting approval.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4"><h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Approved plans</h2></div>
                    <div class="divide-y divide-slate-100">
                        @forelse($approvedPlans as $plan)
                            @php($usedVehicleIds = $plan->vehicleAssignments->pluck('vehicle_id'))
                            <div class="p-4" x-data="{
                                addingVehicle: false,
                                rejecting: false,
                                editingAssignmentId: null,
                                vehicleNoInput: '',
                                availableVehicles: {{ Js::from($planVehicles->reject(fn ($v) => $usedVehicleIds->contains($v->id))->values()->map(fn ($v) => ['id' => $v->id, 'vehicle_no' => $v->vehicle_no, 'vehicle_type' => $v->vehicle_type, 'capacity' => $v->capacity])) }},
                                get matchedVehicle() {
                                    const needle = this.vehicleNoInput.trim().toLowerCase();
                                    if (! needle) return null;
                                    return this.availableVehicles.find(v => v.vehicle_no.toLowerCase() === needle) ?? null;
                                },
                            }">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('dispatch-plans.show', $plan) }}" class="truncate text-sm font-semibold text-slate-800 hover:text-blue-600">{{ $plan->plan_no }}</a>
                                        <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-500">
                                            <div><dt class="inline font-semibold text-slate-600">Date:</dt> <dd class="inline">{{ $plan->plan_date->format('d M Y') }}</dd></div>
                                            <div><dt class="inline font-semibold text-slate-600">Route:</dt> <dd class="inline">{{ $plan->route ?? '—' }}</dd></div>
                                            <div><dt class="inline font-semibold text-slate-600">Dealers:</dt> <dd class="inline">{{ $plan->farmerEstimates->pluck('dealer.dealer_name')->filter()->unique()->implode(', ') ?: '—' }}</dd></div>
                                            <div><dt class="inline font-semibold text-slate-600">Plants (est.):</dt> <dd class="inline">{{ $plan->farmerEstimates->sum('plant_quantity') ? number_format($plan->farmerEstimates->sum('plant_quantity')) : '—' }}</dd></div>
                                            <div class="col-span-2"><dt class="inline font-semibold text-slate-600">Approved by:</dt> <dd class="inline">{{ $plan->approvedBy?->name ?? '—' }} · {{ $plan->approved_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                                        </dl>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        @can('update', $plan)<a href="{{ route('dispatch-plans.edit', $plan) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Edit</a>@endcan
                                        @can('reject', $plan)<button type="button" @click="rejecting = ! rejecting" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Reject</button>@endcan
                                        @can('create', [App\Models\VehicleAssignment::class, $plan])
                                            <button type="button" @click="addingVehicle = ! addingVehicle" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">+ Add vehicle</button>
                                        @endcan
                                    </div>
                                </div>

                                @if($plan->farmerEstimates->isNotEmpty())
                                    <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Dealers &amp; bookings</p>
                                        <div class="space-y-3">
                                            @foreach($plan->farmerEstimates->groupBy('dealer_id') as $dealerGroup)
                                                <div>
                                                    <p class="text-xs font-semibold text-slate-800">{{ $dealerGroup->first()->dealer?->dealer_name ?? '—' }}
                                                        <span class="font-normal text-slate-500">· {{ number_format($dealerGroup->sum('plant_quantity')) }} plants</span>
                                                    </p>
                                                    <dl class="mt-1 space-y-1">
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

                                @if($plan->vehicleAssignments->isNotEmpty())
                                    <div class="mt-3 space-y-2">
                                        @foreach($plan->vehicleAssignments as $assignment)
                                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="min-w-0 truncate text-xs text-slate-600"><span class="font-semibold text-slate-800">{{ $assignment->vehicle->vehicle_no }}</span> {{ collect([$assignment->driver_name, $assignment->start_km ? 'Start KM: '.number_format($assignment->start_km) : null])->filter()->implode(' · ') }}</p>
                                                    <div class="flex shrink-0 items-center gap-2">
                                                        @can('update', $assignment)
                                                            <button type="button" @click="editingAssignmentId = editingAssignmentId === {{ $assignment->id }} ? null : {{ $assignment->id }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">Change</button>
                                                        @endcan
                                                        @can('delete', $assignment)
                                                            <form method="POST" action="{{ route('vehicle-assignments.destroy', $assignment) }}" onsubmit="return confirm('Remove this vehicle from the plan?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-rose-600 hover:text-rose-800">Delete</button></form>
                                                        @endcan
                                                    </div>
                                                </div>

                                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        <span @class([
                                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                                            'bg-blue-100 text-blue-700' => $assignment->loading_status === 'pending',
                                                            'bg-amber-100 text-amber-700' => $assignment->loading_status === 'loading',
                                                            'bg-emerald-100 text-emerald-700' => $assignment->loading_status === 'completed',
                                                        ])>Loading: {{ ucfirst($assignment->loading_status) }}</span>
                                                        @if($assignment->dispatch_status)
                                                            <span @class([
                                                                'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                                                'bg-slate-100 text-slate-600' => $assignment->dispatch_status === 'available',
                                                                'bg-amber-100 text-amber-700' => $assignment->dispatch_status === 'partially_dispatched',
                                                                'bg-emerald-100 text-emerald-700' => $assignment->dispatch_status === 'completed',
                                                            ])>{{ $assignment->dispatch_status === 'partially_dispatched' ? 'Partially dispatched' : ucfirst($assignment->dispatch_status) }}</span>
                                                        @endif
                                                        @if($assignment->active_dispatch?->vehicle_status_label)
                                                            <span class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-700">Vehicle: {{ $assignment->active_dispatch->vehicle_status_label }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="flex shrink-0 items-center gap-3">
                                                        @if($assignment->loading_status !== 'completed')
                                                            @can('loadVehicle', $assignment)
                                                                @if($assignment->items->isEmpty())
                                                                    <span class="text-xs text-slate-400">No bookings on this vehicle yet</span>
                                                                @else
                                                                    <form method="POST" action="{{ route('vehicle-assignments.load-vehicle', $assignment) }}" onsubmit="return confirm('Dispatch this vehicle? This locks the vehicle/bookings/quantities and automatically generates the delivery challan.')">
                                                                        @csrf
                                                                        <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">🚚 Dispatch Vehicle</button>
                                                                    </form>
                                                                @endif
                                                            @endcan
                                                        @elseif($assignment->active_dispatch)
                                                            @can('vehicleOut', $assignment->active_dispatch)
                                                                <form method="POST" action="{{ route('dispatches.vehicle-out', $assignment->active_dispatch) }}" onsubmit="return confirm('Confirm the loaded vehicle has physically left the nursery? No further changes are allowed after this.')">
                                                                    @csrf
                                                                    <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">🚛 Vehicle Left Nursery</button>
                                                                </form>
                                                            @endcan
                                                            <a href="{{ route('dispatches.show', $assignment->active_dispatch) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">View challan →</a>
                                                        @elseif($assignment->dispatch_status === 'completed')
                                                            <span class="text-xs font-semibold text-emerald-700">✓ Dispatched</span>
                                                        @else
                                                            @can('create', \App\Models\Dispatch::class)
                                                                <a href="{{ route('dispatches.create', ['vehicle_assignment_id' => $assignment->id]) }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">Create dispatch →</a>
                                                            @endcan
                                                        @endif
                                                    </div>
                                                </div>
                                                @can('update', $assignment)
                                                    <div x-show="editingAssignmentId === {{ $assignment->id }}" x-cloak class="mt-3 rounded-xl border border-blue-200 bg-blue-50 p-3">
                                                        <form method="POST" action="{{ route('vehicle-assignments.update', $assignment) }}" class="grid gap-3 sm:grid-cols-2">
                                                            @csrf @method('PUT')
                                                            <div>
                                                                <label class="text-xs font-semibold text-slate-600">Vehicle</label>
                                                                <select name="vehicle_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                    @php($otherUsedVehicleIds = $plan->vehicleAssignments->where('id', '!=', $assignment->id)->pluck('vehicle_id'))
                                                                    @foreach($planVehicles as $vehicle)
                                                                        @unless($otherUsedVehicleIds->contains($vehicle->id))
                                                                            <option value="{{ $vehicle->id }}" @selected($vehicle->id === $assignment->vehicle_id)>{{ $vehicle->vehicle_no }}{{ $vehicle->capacity ? " ({$vehicle->capacity})" : '' }}</option>
                                                                        @endunless
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="text-xs font-semibold text-slate-600">Marketing officer</label>
                                                                <select name="marketing_user_id" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                    <option value="">—</option>
                                                                    @foreach($planMarketingUsers as $marketingUser)
                                                                        <option value="{{ $marketingUser->id }}" @selected($marketingUser->id === $assignment->marketing_user_id)>{{ $marketingUser->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="text-xs font-semibold text-slate-600">Estimated departure</label>
                                                                <input type="time" name="estimated_departure_time" value="{{ $assignment->estimated_departure_time }}" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs font-semibold text-slate-600">Start KM <span class="text-rose-500">*</span></label>
                                                                <input type="number" min="0" name="start_km" value="{{ $assignment->start_km }}" required class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs font-semibold text-slate-600">Driver name</label>
                                                                <input name="driver_name" value="{{ $assignment->driver_name }}" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs font-semibold text-slate-600">Driver mobile</label>
                                                                <input name="driver_mobile" value="{{ $assignment->driver_mobile }}" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs font-semibold text-slate-600">Helper name</label>
                                                                <input name="helper_name" value="{{ $assignment->helper_name }}" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                            </div>
                                                            <div class="sm:col-span-2">
                                                                <label class="text-xs font-semibold text-slate-600">Remarks</label>
                                                                <input name="remarks" value="{{ $assignment->remarks }}" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                            </div>
                                                            <div class="sm:col-span-2 flex justify-end gap-2">
                                                                <button type="button" @click="editingAssignmentId = null" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                                                                <button class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Save changes</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                @endcan
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @can('reject', $plan)
                                    <div x-show="rejecting" x-cloak class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3">
                                        <form method="POST" action="{{ route('dispatch-plans.reject', $plan) }}" class="flex flex-wrap items-end gap-2">
                                            @csrf
                                            <div class="flex-1"><label class="text-xs font-semibold text-slate-600">Reason for rejection</label><input type="text" name="rejection_reason" required minlength="5" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-rose-500 focus:ring-rose-500"></div>
                                            <button class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Confirm reject</button>
                                        </form>
                                    </div>
                                @endcan
                                @can('create', [App\Models\VehicleAssignment::class, $plan])
                                    <div x-show="addingVehicle" x-cloak class="mt-3 rounded-xl border border-blue-200 bg-blue-50 p-4">
                                        <form method="POST" action="{{ route('vehicle-assignments.store', $plan) }}" class="grid gap-3 sm:grid-cols-2">
                                            @csrf
                                            <div class="sm:col-span-2">
                                                <label class="text-xs font-semibold text-slate-600">Vehicle number</label>
                                                <input type="text" list="vehicle-no-options-{{ $plan->id }}" x-model="vehicleNoInput" placeholder="Type vehicle number" autocomplete="off" class="mt-1 block w-full rounded-lg border-slate-300 text-xs uppercase shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <datalist id="vehicle-no-options-{{ $plan->id }}">
                                                    <template x-for="v in availableVehicles" :key="v.id"><option :value="v.vehicle_no"></option></template>
                                                </datalist>
                                                <input type="hidden" name="vehicle_id" :value="matchedVehicle ? matchedVehicle.id : ''">

                                                <template x-if="matchedVehicle">
                                                    <p class="mt-1 text-xs text-emerald-700">✓ <span x-text="matchedVehicle.vehicle_type || 'Type not set'"></span><template x-if="matchedVehicle.capacity"><span> · <span x-text="matchedVehicle.capacity"></span> capacity</span></template></p>
                                                </template>
                                                <template x-if="vehicleNoInput.trim() && ! matchedVehicle">
                                                    <p class="mt-1 text-xs text-amber-700">Not registered yet. <a :href="'{{ route('vehicles.create') }}?vehicle_no=' + encodeURIComponent(vehicleNoInput.trim()) + '&return_dispatch_plan_id={{ $plan->id }}'" class="font-semibold underline">+ Register this vehicle</a></p>
                                                </template>
                                                @error('vehicle_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-600">Start KM <span class="text-rose-500">*</span></label>
                                                <input type="number" min="0" name="start_km" required class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-600">Marketing officer</label>
                                                <select name="marketing_user_id" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    <option value="">—</option>
                                                    @foreach($planMarketingUsers as $marketingUser)
                                                        <option value="{{ $marketingUser->id }}">{{ $marketingUser->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-600">Estimated departure</label>
                                                <input type="time" name="estimated_departure_time" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-600">Driver name</label>
                                                <input name="driver_name" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-600">Driver mobile</label>
                                                <input name="driver_mobile" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-600">Helper name</label>
                                                <input name="helper_name" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label class="text-xs font-semibold text-slate-600">Remarks</label>
                                                <input name="remarks" class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                            <div class="sm:col-span-2 flex justify-end gap-2">
                                                <button type="button" @click="addingVehicle = false" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                                                <button class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Add vehicle</button>
                                            </div>
                                        </form>
                                    </div>
                                @endcan
                            </div>
                        @empty
                            <p class="px-6 py-8 text-center text-sm text-slate-500">No approved plans yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endcan

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <input name="search" value="{{ request('search') }}" placeholder="Search dispatch no, challan, driver..." class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:max-w-xs">

                    <select name="dealer" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All dealers</option>
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected(request('dealer') == $dealer->id)>{{ $dealer->dealer_name }}</option>
                        @endforeach
                    </select>

                    <select name="vehicle" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All vehicles</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected(request('vehicle') == $vehicle->id)>{{ $vehicle->vehicle_no }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Any status</option>
                        @foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach
                    </select>

                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" title="From date">
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" title="To date">

                    @if ($trashed)<input type="hidden" name="trashed" value="1">@endif

                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    <a href="{{ route('dispatches.index', $trashed ? ['trashed' => 1] : []) }}" class="rounded-xl px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-100">Clear</a>

                    <div class="sm:ml-auto">
                        @can('viewAny', \App\Models\Dispatch::class)
                            @if ($trashed)
                                <a href="{{ route('dispatches.index') }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50">← Back to active dispatches</a>
                            @else
                                <a href="{{ route('dispatches.index', ['trashed' => 1]) }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100">View deleted dispatches</a>
                            @endif
                        @endcan
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dispatch</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dealer / Farmer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Vehicle / Driver</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Qty</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($dispatches as $dispatch)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-900">{{ $dispatch->dispatch_no }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $dispatch->lines->count() }} booking{{ $dispatch->lines->count() === 1 ? '' : 's' }} · {{ $dispatch->dispatch_date?->format('d M Y') }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $dispatch->lines->pluck('dealer.dealer_name')->filter()->unique()->implode(', ') ?: '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $dispatch->vehicle?->vehicle_no ?? '—' }}<br><span class="text-xs text-slate-400">{{ $dispatch->driver_name ?? '—' }}</span></td>
                                <td class="px-6 py-4 text-right text-sm text-slate-600">{{ number_format($dispatch->lines->sum('dispatch_qty')) }}<br><span class="text-xs text-slate-400">Rem {{ number_format($dispatch->lines->sum('remaining_qty')) }}</span></td>
                                <td class="px-6 py-4"><x-dispatch-status-badge :status="$dispatch->status" /></td>
                                <td class="px-6 py-4 text-right">
                                    @if ($trashed)
                                        @can('restore', $dispatch)
                                            <form method="POST" action="{{ route('dispatches.restore', $dispatch) }}" class="inline">@csrf<button class="text-sm font-semibold text-emerald-600 hover:text-emerald-800">Restore</button></form>
                                        @endcan
                                    @else
                                        @can('view', $dispatch)
                                            <a href="{{ route('dispatches.show', $dispatch) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">View</a>
                                            @if($dispatch->challan_no)
                                                <span class="text-slate-300">·</span>
                                                <a href="{{ route('dispatches.print', $dispatch) }}" target="_blank" class="text-sm font-semibold text-blue-600 hover:text-blue-800">View Challan</a>
                                            @endif
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">No dispatches found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $dispatches->links() }}</div>
        </div>
    </div>
</x-app-layout>
