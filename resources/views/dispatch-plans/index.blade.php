<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">TRANSPORT</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Dispatch Planning</h1>
                <p class="mt-2 text-sm text-slate-500">Plan a day's routes, then group bookings onto vehicles.</p>
            </div>
            @can('create', App\Models\DispatchPlan::class)
                <a href="{{ route('dispatch-plans.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 4v16m8-8H4" /></svg>New plan</a>
            @endcan
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Plan</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Route</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dealers</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Total plants</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Approval</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Vehicles</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($plans as $plan)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm font-semibold text-slate-800">{{ $plan->plan_no }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $plan->plan_date->format('d M Y') }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $plan->route ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $plan->farmerEstimates->pluck('dealer.dealer_name')->filter()->unique()->implode(', ') ?: '—' }}</td>
                                <td class="px-6 py-4 text-right text-sm text-slate-600">{{ $plan->total_plant_quantity ? number_format($plan->total_plant_quantity) : '—' }}</td>
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-blue-100 text-blue-700' => $plan->approval_status === 'pending',
                                        'bg-emerald-100 text-emerald-700' => $plan->approval_status === 'approved',
                                        'bg-rose-100 text-rose-700' => $plan->approval_status === 'rejected',
                                    ])>{{ ucfirst($plan->approval_status) }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $plan->vehicle_assignments_count }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('dispatch-plans.show', $plan) }}" class="mr-3 text-sm font-semibold text-blue-600 hover:text-blue-800">Open</a>
                                    @can('update', $plan)<a href="{{ route('dispatch-plans.edit', $plan) }}" class="mr-3 text-sm font-semibold text-slate-600 hover:text-slate-800">Edit</a>@endcan
                                    @can('delete', $plan)<form method="POST" action="{{ route('dispatch-plans.destroy', $plan) }}" class="inline" onsubmit="return confirm('Delete this dispatch plan?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 hover:text-rose-800">Delete</button></form>@endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-12 text-center text-sm text-slate-500">No dispatch plans yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $plans->links() }}</div>
        </div>
    </div>
</x-app-layout>
