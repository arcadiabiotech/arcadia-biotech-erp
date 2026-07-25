<x-app-layout>
    <div class="mx-auto max-w-5xl" x-data="{ tab: 'details', cancelling: false }">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('invoices.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to invoices</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $invoice->invoice_no }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $invoice->dispatch?->dispatch_no }} · {{ $invoice->dealer?->dealer_name }} · {{ $invoice->lines->count() }} booking{{ $invoice->lines->count() === 1 ? '' : 's' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-invoice-status-badge :status="$invoice->status" class="!px-3 !py-1.5" />
                <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Print invoice</a>
                <a href="{{ route('invoices.pdf', $invoice) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Download PDF</a>
                @can('update', $invoice)<a href="{{ route('invoices.edit', $invoice) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Edit</a>@endcan
            </div>
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="mb-6 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            @can('generate', $invoice)
                <form method="POST" action="{{ route('invoices.generate', $invoice) }}" onsubmit="return confirm('Generate this invoice? The dealer ledger will be debited for the grand total.')">@csrf<button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Generate invoice</button></form>
            @endcan
            @if(in_array($invoice->status, ['generated', 'partially_paid']))
                @can('create', \App\Models\Payment::class)
                    <a href="{{ route('payments.create', ['invoice_id' => $invoice->id]) }}" class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Receive payment</a>
                @endcan
            @endif
            @can('cancel', $invoice)
                <button type="button" @click="cancelling = ! cancelling" class="rounded-xl border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Cancel invoice</button>
            @endcan
            @can('unlock', $invoice)
                <form method="POST" action="{{ route('invoices.unlock', $invoice) }}">@csrf<button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unlock</button></form>
            @endcan
            @can('delete', $invoice)
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" class="ml-auto" onsubmit="return confirm('Delete this invoice?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 hover:text-rose-800">Delete invoice</button></form>
            @endcan
        </div>

        @can('cancel', $invoice)
            <div x-show="cancelling" x-cloak class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4">
                <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex-1"><label class="text-xs font-semibold text-slate-600">Reason for cancellation (required)</label><input type="text" name="reason" required minlength="5" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500"></div>
                    <button class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Confirm cancel</button>
                </form>
            </div>
        @endcan

        <div class="mb-6 flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            @foreach(['details' => 'Details', 'payments' => 'Payments', 'timeline' => 'Timeline', 'history' => 'Audit history'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-lg px-4 py-2 text-sm font-semibold transition">{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'details'" class="grid gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Bookings on this invoice</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="py-2 pr-3 text-left">Booking</th>
                                <th class="py-2 pr-3 text-left">Farmer</th>
                                <th class="py-2 pr-3 text-right">Qty</th>
                                <th class="py-2 pr-3 text-right">Rate</th>
                                <th class="py-2 pr-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($invoice->lines as $line)
                                <tr>
                                    <td class="py-2 pr-3 font-medium text-slate-800">{{ $line->booking?->booking_no }}</td>
                                    <td class="py-2 pr-3 text-slate-600">{{ $line->farmer?->farmer_name }}</td>
                                    <td class="py-2 pr-3 text-right text-slate-600">{{ number_format($line->qty) }}</td>
                                    <td class="py-2 pr-3 text-right text-slate-600">₹{{ number_format((float) $line->rate, 2) }}</td>
                                    <td class="py-2 pr-3 text-right font-semibold text-slate-800">₹{{ number_format((float) $line->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Source</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Dispatch</dt><dd class="font-medium text-slate-800">{{ $invoice->dispatch?->dispatch_no }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Invoice date</dt><dd class="font-medium text-slate-800">{{ $invoice->invoice_date?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Dealer</dt><dd class="font-medium text-slate-800">{{ $invoice->dealer?->dealer_name }}</dd></div>
                </dl>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Amounts</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="font-medium text-slate-800">₹{{ number_format((float) $invoice->subtotal, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="font-medium text-slate-800">₹{{ number_format((float) $invoice->discount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd class="font-medium text-slate-800">₹{{ number_format((float) $invoice->tax, 2) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-3"><dt class="font-semibold text-slate-600">Grand total</dt><dd class="font-semibold text-slate-900">₹{{ number_format((float) $invoice->grand_total, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Paid</dt><dd class="font-medium text-emerald-700">₹{{ number_format((float) $invoice->paid_amount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Balance</dt><dd class="font-medium {{ (float) $invoice->balance_amount > 0 ? 'text-rose-600' : 'text-emerald-700' }}">₹{{ number_format((float) $invoice->balance_amount, 2) }}</dd></div>
                </dl>
            </div>
            @if($invoice->remarks)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-slate-400">Remarks</h2>
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $invoice->remarks }}</p>
                </div>
            @endif
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Record</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between"><dt class="text-slate-500">Created by</dt><dd class="font-medium text-slate-800">{{ $invoice->createdBy?->name ?? '—' }} · {{ $invoice->created_at?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Last updated by</dt><dd class="font-medium text-slate-800">{{ $invoice->updatedBy?->name ?? '—' }} · {{ $invoice->updated_at?->format('d M Y') }}</dd></div>
                </dl>
            </div>
        </div>

        <div x-show="tab === 'payments'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Payment</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Mode</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Reference</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Amount</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Receipt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($invoice->payments as $payment)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4"><p class="font-semibold text-slate-900">{{ $payment->payment_no }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $payment->payment_date?->format('d M Y') }}</p></td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $payment->payment_mode }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $payment->reference_no ?? '—' }}</td>
                            <td class="px-6 py-4 text-right text-sm font-medium text-slate-800">₹{{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="px-6 py-4 text-right"><a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="text-sm font-semibold text-blue-600 hover:text-blue-800">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">No payments recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'timeline'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @forelse($activity as $entry)
                <div class="relative pb-8 pl-8 last:pb-0">
                    <span class="absolute left-0 top-1 grid h-5 w-5 place-items-center rounded-full bg-blue-600"><span class="h-2 w-2 rounded-full bg-white"></span></span>
                    @unless($loop->last)<span class="absolute left-[9px] top-6 h-full w-px bg-slate-200"></span>@endunless
                    <p class="text-sm font-semibold capitalize text-slate-800">Invoice {{ $entry->action }}{{ $entry->remarks ? ' — '.$entry->remarks : '' }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $entry->created_at->format('d M Y, h:i A') }} · by {{ $entry->user?->name ?? 'System' }}</p>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-slate-500">No timeline events yet.</p>
            @endforelse
        </div>

        <div x-show="tab === 'history'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse($activity as $entry)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold capitalize text-slate-800">{{ $entry->action }}</span>
                            <span class="text-xs text-slate-400">{{ $entry->created_at->format('d M Y, h:i A') }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">by {{ $entry->user?->name ?? 'System' }}@if($entry->remarks) · {{ $entry->remarks }} @endif</p>
                        @if($entry->old_values && $entry->new_values)
                            <div class="mt-2 space-y-1 rounded-lg bg-slate-50 p-3 text-xs">
                                @foreach(array_intersect_key($entry->new_values, $entry->old_values) as $field => $newValue)
                                    @if(($entry->old_values[$field] ?? null) != $newValue)
                                        <p><span class="font-semibold text-slate-600">{{ $field }}:</span> <span class="text-rose-600 line-through">{{ is_array($entry->old_values[$field] ?? null) ? '—' : ($entry->old_values[$field] ?? '—') }}</span> → <span class="text-emerald-700">{{ is_array($newValue) ? '—' : ($newValue ?? '—') }}</span></p>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="px-6 py-12 text-center text-sm text-slate-500">No activity recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
