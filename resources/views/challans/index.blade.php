<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">LOGISTICS</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Challans</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $challans->total() }} challan{{ $challans->total() === 1 ? '' : 's' }} issued.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('challans.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16" /></svg>Export Excel</a>
            </div>
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <input name="search" value="{{ request('search') }}" placeholder="Search challan, dispatch, dealer..." class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:max-w-xs">

                    <select name="dealer" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All dealers</option>
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected(request('dealer') == $dealer->id)>{{ $dealer->dealer_name }}</option>
                        @endforeach
                    </select>

                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    <a href="{{ route('challans.index') }}" class="rounded-xl px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-100">Clear</a>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Challan</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dispatch</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dealer / Farmer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Vehicle</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($challans as $dispatch)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4"><p class="font-semibold text-slate-900">{{ $dispatch->challan_no }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $dispatch->dispatch_date?->format('d M Y') }}</p></td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $dispatch->dispatch_no }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $dispatch->lines->pluck('dealer.dealer_name')->filter()->unique()->implode(', ') ?: '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $dispatch->vehicle?->vehicle_no ?? '—' }}</td>
                                <td class="px-6 py-4"><x-dispatch-status-badge :status="$dispatch->status" /></td>
                                <td class="px-6 py-4 text-right">
                                    @can('view', $dispatch)
                                        <a href="{{ route('dispatches.show', $dispatch) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">View</a>
                                        <span class="text-slate-300">·</span>
                                    @endcan
                                    <a href="{{ route('dispatches.print', $dispatch) }}" target="_blank" class="text-sm font-semibold text-slate-600 hover:text-slate-800">Print</a>
                                    <span class="text-slate-300">·</span>
                                    <a href="{{ route('dispatches.pdf', $dispatch) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">PDF</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">No challans issued yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $challans->links() }}</div>
        </div>
    </div>
</x-app-layout>
