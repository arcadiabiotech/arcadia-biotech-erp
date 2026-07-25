<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('ledger.dealer', $farmer->dealer_id) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to dealer statement</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $farmer->farmer_name }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $farmer->farmer_code }} · Farmer ledger statement</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-right shadow-sm">
                    <p class="text-xs text-slate-500">Net balance</p>
                    <p class="text-lg font-bold {{ $balance > 0 ? 'text-rose-600' : 'text-emerald-600' }}">₹{{ number_format($balance, 2) }}</p>
                </div>
                <a href="{{ route('ledger.farmer-export', $farmer) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Export Excel</a>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Module</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Remarks</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Debit</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($entries as $entry)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $entry->entry_date?->format('d M Y') }}</td>
                            <td class="px-6 py-4 text-sm capitalize text-slate-600">{{ $entry->module }}</td>
                            <td class="px-6 py-4 text-sm text-slate-500">{{ $entry->remarks ?? '—' }}</td>
                            <td class="px-6 py-4 text-right text-sm font-medium text-rose-600">{{ (float) $entry->debit > 0 ? '₹'.number_format((float) $entry->debit, 2) : '—' }}</td>
                            <td class="px-6 py-4 text-right text-sm font-medium text-emerald-600">{{ (float) $entry->credit > 0 ? '₹'.number_format((float) $entry->credit, 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">No ledger entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="border-t border-slate-200 px-4 py-3">{{ $entries->links() }}</div>
        </div>
    </div>
</x-app-layout>
