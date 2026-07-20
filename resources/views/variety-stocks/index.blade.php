<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <p class="text-sm font-semibold text-blue-600">INVENTORY</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">Variety Stock</h1>
            <p class="mt-2 text-sm text-slate-500">Set actual plant stock per variety. Available stock is derived automatically as Actual − Reserved.</p>
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Variety</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actual stock</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Reserved</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Available</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Update</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($stocks as $stock)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-semibold text-slate-900">{{ $stock->variety }}</td>
                            <td class="px-6 py-4 text-right text-sm text-slate-600">{{ number_format($stock->actual_qty) }}</td>
                            <td class="px-6 py-4 text-right text-sm text-amber-600">{{ number_format($stock->reservedQty()) }}</td>
                            <td class="px-6 py-4 text-right text-sm font-semibold text-emerald-700">{{ number_format($stock->availableQty()) }}</td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" action="{{ route('variety-stocks.update', $stock) }}" class="flex items-center justify-end gap-2">
                                    @csrf @method('PUT')
                                    <input type="number" min="0" name="actual_qty" value="{{ $stock->actual_qty }}" class="w-28 rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <button class="rounded-xl bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
