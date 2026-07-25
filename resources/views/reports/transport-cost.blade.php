<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('reports.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to reports</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">Transport Cost Report</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $dispatches->count() }} dispatch{{ $dispatches->count() === 1 ? '' : 'es' }} with recorded transport cost.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.transport-cost.csv', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16" /></svg>Export Excel</a>
            </div>
        </div>

        <div class="mb-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total KM travelled</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($overall['total_km']) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total transport expense</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">₹{{ number_format($overall['total_expense'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Avg cost / dispatch</p>
                <p class="mt-2 text-2xl font-bold text-blue-700">₹{{ number_format($overall['avg_cost_per_dispatch'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Avg cost / plant</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">₹{{ number_format($overall['avg_cost_per_plant'], 2) }}</p>
            </div>
        </div>

        <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <select name="vehicle_id" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All vehicles</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected(request('vehicle_id') == $vehicle->id)>{{ $vehicle->vehicle_no }}</option>
                        @endforeach
                    </select>

                    <select name="dealer" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All dealers</option>
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected(request('dealer') == $dealer->id)>{{ $dealer->dealer_name }}</option>
                        @endforeach
                    </select>

                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" title="Return date from">
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" title="Return date to">

                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    <a href="{{ route('reports.transport-cost') }}" class="rounded-xl px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-100">Clear</a>
                </form>
            </div>
        </div>

        <div class="mb-6 grid gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Vehicle-wise</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-400">
                            <tr><th class="py-2 pr-3 text-left">Vehicle</th><th class="py-2 pr-3 text-right">Trips</th><th class="py-2 pr-3 text-right">KM</th><th class="py-2 pr-3 text-right">Expense</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($vehicleSummary as $row)
                                <tr>
                                    <td class="py-2 pr-3 font-medium text-slate-800">{{ $row['vehicle']?->vehicle_no ?? '—' }}</td>
                                    <td class="py-2 pr-3 text-right text-slate-600">{{ $row['trips'] }}</td>
                                    <td class="py-2 pr-3 text-right text-slate-600">{{ number_format($row['total_km']) }}</td>
                                    <td class="py-2 pr-3 text-right font-semibold text-slate-800">₹{{ number_format($row['total_expense'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-slate-400">No data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Dealer-wise</h2>
                <p class="mb-3 text-xs text-slate-400">Cost split proportionally by each dealer's share of plants shipped.</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-400">
                            <tr><th class="py-2 pr-3 text-left">Dealer</th><th class="py-2 pr-3 text-right">Plants</th><th class="py-2 pr-3 text-right">Allocated cost</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($dealerSummary as $row)
                                <tr>
                                    <td class="py-2 pr-3 font-medium text-slate-800">{{ $row['dealer']?->dealer_name ?? '—' }}</td>
                                    <td class="py-2 pr-3 text-right text-slate-600">{{ number_format($row['qty']) }}</td>
                                    <td class="py-2 pr-3 text-right font-semibold text-slate-800">₹{{ number_format($row['allocated_cost'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-slate-400">No data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Monthly</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-400">
                            <tr><th class="py-2 pr-3 text-left">Month</th><th class="py-2 pr-3 text-right">KM</th><th class="py-2 pr-3 text-right">Expense</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($monthlySummary as $row)
                                <tr>
                                    <td class="py-2 pr-3 font-medium text-slate-800">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $row['month'])->format('M Y') }}</td>
                                    <td class="py-2 pr-3 text-right text-slate-600">{{ number_format($row['total_km']) }}</td>
                                    <td class="py-2 pr-3 text-right font-semibold text-slate-800">₹{{ number_format($row['total_expense'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-slate-400">No data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Dispatch-wise detail</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Dispatch</th>
                            <th class="px-4 py-3 text-left">Vehicle</th>
                            <th class="px-4 py-3 text-left">Return date</th>
                            <th class="px-4 py-3 text-right">Odo Start</th>
                            <th class="px-4 py-3 text-right">Odo End</th>
                            <th class="px-4 py-3 text-right">Total KM</th>
                            <th class="px-4 py-3 text-right">Transport Cost</th>
                            <th class="px-4 py-3 text-right">Total Expense</th>
                            <th class="px-4 py-3 text-right">Cost/Plant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($dispatches as $dispatch)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3"><a href="{{ route('dispatches.show', $dispatch) }}" class="font-semibold text-blue-600 hover:text-blue-800">{{ $dispatch->dispatch_no }}</a></td>
                                <td class="px-4 py-3 text-slate-600">{{ $dispatch->vehicle?->vehicle_no ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $dispatch->vehicle_returned_at?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $dispatch->odometer_start ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $dispatch->odometer_end ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $dispatch->total_km !== null ? number_format($dispatch->total_km) : '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $dispatch->transport_cost !== null ? '₹'.number_format($dispatch->transport_cost, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ $dispatch->total_transport_expense !== null ? '₹'.number_format($dispatch->total_transport_expense, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $dispatch->cost_per_plant !== null ? '₹'.number_format($dispatch->cost_per_plant, 2) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-12 text-center text-sm text-slate-500">No dispatches with recorded transport cost match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
