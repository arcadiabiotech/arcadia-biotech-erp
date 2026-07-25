<x-app-layout>
    <div class="mx-auto max-w-3xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('payments.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to payments</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $payment->payment_no }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $payment->dealer?->dealer_name }} · {{ $payment->farmer?->farmer_name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Print receipt</a>
                <a href="{{ route('payments.receipt-pdf', $payment) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Download PDF</a>
            </div>
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Payment details</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Invoice</dt><dd class="font-medium text-slate-800"><a href="{{ route('invoices.show', $payment->invoice) }}" class="text-blue-600 hover:underline">{{ $payment->invoice?->invoice_no }}</a></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Payment date</dt><dd class="font-medium text-slate-800">{{ $payment->payment_date?->format('d M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Mode</dt><dd class="font-medium text-slate-800">{{ $payment->payment_mode }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Reference no.</dt><dd class="font-medium text-slate-800">{{ $payment->reference_no ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Bank</dt><dd class="font-medium text-slate-800">{{ $payment->bank_name ?? '—' }}</dd></div>
                <div class="flex justify-between border-t border-slate-100 pt-3"><dt class="font-semibold text-slate-600">Amount</dt><dd class="font-semibold text-slate-900">₹{{ number_format((float) $payment->amount, 2) }}</dd></div>
            </dl>
            @if($payment->remarks)
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <h3 class="mb-1 text-xs font-bold uppercase tracking-wide text-slate-400">Remarks</h3>
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $payment->remarks }}</p>
                </div>
            @endif
            <div class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
                Received by {{ $payment->createdBy?->name ?? '—' }} · {{ $payment->created_at?->format('d M Y, h:i A') }}
            </div>
        </div>
    </div>
</x-app-layout>
