<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <a href="{{ $invoice->exists ? route('invoices.show', $invoice) : route('invoices.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $invoice->exists ? 'Edit invoice' : 'Generate invoice' }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $invoice->exists ? $invoice->invoice_no : 'An invoice number is generated automatically on save, as a Draft. Use "Generate" on the invoice page to finalize it and debit the ledger.' }}</p>
        </div>

        <form method="POST" action="{{ $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @if(! $invoice->exists) x-data="{ pair: '' }" @endif>
            @csrf @if($invoice->exists) @method('PUT') @endif

            <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Dispatch</div>
            <div class="grid gap-6 sm:grid-cols-2">
                @if($invoice->exists)
                    <div class="sm:col-span-2">
                        <label class="text-sm font-semibold text-slate-700">Dispatch</label>
                        <p class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-600">{{ $invoice->dispatch->dispatch_no }} — {{ $invoice->dealer?->dealer_name }} ({{ number_format($invoice->lines->sum('qty')) }} plants dispatched)</p>
                    </div>
                @else
                    <div class="sm:col-span-2">
                        <label class="text-sm font-semibold text-slate-700">Completed dispatch / dealer</label>
                        <select x-model="pair" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Select a completed dispatch/dealer —</option>
                            @foreach($dealerGroups as $group)
                                <option value="{{ $group['dispatch']->id }}:{{ $group['dealer']->id }}" @selected(old('dispatch_id') == $group['dispatch']->id && old('dealer_id') == $group['dealer']->id)>{{ $group['dispatch']->dispatch_no }} — {{ $group['dealer']->dealer_name }} ({{ $group['lines']->count() }} booking{{ $group['lines']->count() === 1 ? '' : 's' }}, {{ number_format($group['lines']->sum('total_qty')) }} plants)</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="dispatch_id" :value="pair.split(':')[0]">
                        <input type="hidden" name="dealer_id" :value="pair.split(':')[1]">
                        @error('dispatch_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endif
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Invoice details</div>
            <div class="grid gap-6 sm:grid-cols-3">
                <div><label class="text-sm font-semibold text-slate-700">Invoice date</label><input type="date" name="invoice_date" value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('invoice_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Discount</label><input type="number" step="0.01" min="0" name="discount" value="{{ old('discount', $invoice->discount ?? 0) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('discount')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Tax</label><input type="number" step="0.01" min="0" name="tax" value="{{ old('tax', $invoice->tax ?? 0) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('tax')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </div>
            @if($invoice->exists)
                <p class="mt-3 text-xs text-slate-500">Subtotal ₹{{ number_format((float) $invoice->subtotal, 2) }} · Grand total recalculates automatically as Subtotal − Discount + Tax.</p>
            @endif

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Notes</div>
            <div><textarea name="remarks" rows="3" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks', $invoice->remarks) }}</textarea>@error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ $invoice->exists ? route('invoices.show', $invoice) : route('invoices.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $invoice->exists ? 'Save changes' : 'Create draft invoice' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
