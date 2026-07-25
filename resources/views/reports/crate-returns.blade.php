<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('reports.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to reports</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">Crate Return Report</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $lines->count() }} booking line{{ $lines->count() === 1 ? '' : 's' }} tracking crates.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.crate-returns.csv', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16" /></svg>Export Excel</a>
                <a href="{{ route('reports.crate-returns.pdf', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16" /></svg>Download PDF</a>
            </div>
        </div>

        <div class="mb-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total crates</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ rtrim(rtrim(number_format($summary['total'], 2), '0'), '.') ?: 0 }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Returned</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">{{ rtrim(rtrim(number_format($summary['returned'], 2), '0'), '.') ?: 0 }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Damaged</p>
                <p class="mt-2 text-2xl font-bold text-rose-700">{{ rtrim(rtrim(number_format($summary['damaged'], 2), '0'), '.') ?: 0 }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pending</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ rtrim(rtrim(number_format($summary['pending'], 2), '0'), '.') ?: 0 }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <input name="dispatch_no" value="{{ request('dispatch_no') }}" placeholder="Search dispatch no..." class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:max-w-xs">

                    <select name="dealer" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All dealers</option>
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected(request('dealer') == $dealer->id)>{{ $dealer->dealer_name }}</option>
                        @endforeach
                    </select>

                    <select name="farmer" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All farmers</option>
                        @foreach($farmers as $farmer)
                            <option value="{{ $farmer->id }}" @selected(request('farmer') == $farmer->id)>{{ $farmer->farmer_name }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Pending + Returned</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending only</option>
                        <option value="completed" @selected(request('status') === 'completed')>Returned only</option>
                    </select>

                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" title="Return date from">
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" title="Return date to">

                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    <a href="{{ route('reports.crate-returns') }}" class="rounded-xl px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-100">Clear</a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Dispatch</th>
                            <th class="px-4 py-3 text-left">Vehicle</th>
                            <th class="px-4 py-3 text-left">Dealer / Farmer</th>
                            <th class="px-4 py-3 text-left">Return date</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Returned</th>
                            <th class="px-4 py-3 text-right">Damaged</th>
                            <th class="px-4 py-3 text-right">Pending</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($lines as $line)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3"><a href="{{ route('dispatches.show', $line->dispatch) }}" class="font-semibold text-blue-600 hover:text-blue-800">{{ $line->dispatch?->dispatch_no }}</a></td>
                                <td class="px-4 py-3 text-slate-600">{{ $line->dispatch?->vehicle?->vehicle_no ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $line->dealer?->dealer_name }} / {{ $line->farmer?->farmer_name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $line->dispatch?->vehicle_returned_at?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-800">{{ rtrim(rtrim(number_format($line->crate_count, 2), '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-700">{{ rtrim(rtrim(number_format((float) ($line->crates_returned ?? 0), 2), '0'), '.') ?: 0 }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-rose-700">{{ rtrim(rtrim(number_format((float) ($line->damage_qty ?? 0), 2), '0'), '.') ?: 0 }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-amber-700">{{ rtrim(rtrim(number_format($line->pending_crates, 2), '0'), '.') ?: 0 }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-700' => $line->pending_crates <= 0,
                                        'bg-amber-100 text-amber-700' => $line->pending_crates > 0,
                                    ])>{{ $line->pending_crates > 0 ? 'Pending' : 'Returned' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-12 text-center text-sm text-slate-500">No crate-tracked dispatches match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
