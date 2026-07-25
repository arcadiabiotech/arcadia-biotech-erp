<x-app-layout>
    <div class="mx-auto max-w-3xl">
        <div class="mb-8">
            <a href="{{ route('payments.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">Receive payment</h1>
            <p class="mt-2 text-sm text-slate-500">A payment number is generated automatically, and the invoice/ledger update automatically once saved.</p>
        </div>

        <form
            x-data="{
                invoices: {{ Js::from($invoices->map(fn ($i) => ['id' => (string) $i->id, 'balance' => (string) $i->balance_amount])) }},
                invoiceId: '{{ old('invoice_id', request('invoice_id')) }}',
                paymentMode: '{{ old('payment_mode') }}',
                get balance() {
                    const invoice = this.invoices.find(i => i.id === this.invoiceId);
                    return invoice ? invoice.balance : null;
                },
            }"
            method="POST" action="{{ route('payments.store') }}"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Invoice</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Generated / partially paid invoice</label>
                    <select name="invoice_id" x-model="invoiceId" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select an invoice —</option>
                        @foreach($invoices as $invoice)
                            <option value="{{ $invoice->id }}" @selected(old('invoice_id', request('invoice_id')) == $invoice->id)>{{ $invoice->invoice_no }} — {{ $invoice->dealer?->dealer_name }} / {{ $invoice->farmer?->farmer_name }} (Balance ₹{{ number_format((float) $invoice->balance_amount, 2) }})</option>
                        @endforeach
                    </select>
                    @error('invoice_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500" x-show="balance !== null">Outstanding balance: ₹<span x-text="balance"></span></p>
                </div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Payment details</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div><label class="text-sm font-semibold text-slate-700">Payment date</label><input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('payment_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Amount</label><input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('amount')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Payment mode</label>
                    <select name="payment_mode" x-model="paymentMode" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select mode —</option>
                        @foreach($paymentModes as $mode)<option value="{{ $mode }}" @selected(old('payment_mode') === $mode)>{{ $mode }}</option>@endforeach
                    </select>
                    @error('payment_mode')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Reference no. <span x-show="paymentMode && paymentMode !== 'Cash'" class="text-rose-500">*</span></label>
                    <input name="reference_no" value="{{ old('reference_no') }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('reference_no')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="text-sm font-semibold text-slate-700">Bank name</label><input name="bank_name" value="{{ old('bank_name') }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('bank_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Notes</div>
            <div><textarea name="remarks" rows="3" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks') }}</textarea>@error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('payments.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Record payment</button>
            </div>
        </form>
    </div>
</x-app-layout>
