<x-app-layout>
    <div class="mx-auto max-w-6xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">ACCOUNTS</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Outstanding report</h1>
                <p class="mt-2 text-sm text-slate-500">Dealer-wise running balance across all invoices and payments.</p>
            </div>
            <a href="{{ route('ledger.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16" /></svg>Export Excel</a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input name="search" value="{{ request('search') }}" placeholder="Search dealer name or code..." class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:max-w-xs">
                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    <a href="{{ route('ledger.outstanding') }}" class="rounded-xl px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-100">Clear</a>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dealer</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Outstanding balance</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($dealers as $dealer)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4"><p class="font-semibold text-slate-900">{{ $dealer->dealer_name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $dealer->dealer_code }}</p></td>
                                <td class="px-6 py-4 text-right text-sm font-medium {{ $dealer->ledger_balance > 0 ? 'text-rose-600' : 'text-emerald-600' }}">₹{{ number_format($dealer->ledger_balance, 2) }}</td>
                                <td class="px-6 py-4 text-right"><a href="{{ route('ledger.dealer', $dealer) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">View statement</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-12 text-center text-sm text-slate-500">No dealers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $dealers->links() }}</div>
        </div>
    </div>
</x-app-layout>
