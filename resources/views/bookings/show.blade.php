<x-app-layout>
    <div class="mx-auto max-w-5xl" x-data="{ tab: 'details', paying: false }">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('bookings.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to bookings</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $booking->booking_no }}
                    @if($booking->sale_type === 'spot')
                        <span class="ml-1 inline-flex rounded-full bg-purple-100 px-2.5 py-1 align-middle text-xs font-semibold text-purple-700">Spot sale</span>
                    @endif
                </h1>
                <p class="mt-2 text-sm text-slate-500">{{ $booking->dealer?->dealer_name }} · {{ $booking->farmer?->farmer_name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-booking-status-badge :status="$booking->approval_status" class="!px-3 !py-1.5" />
                <a href="{{ route('bookings.print', $booking) }}" target="_blank" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Print slip</a>
                <a href="{{ route('bookings.pdf', $booking) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Download PDF</a>
                @can('update', $booking)<a href="{{ route('bookings.edit', $booking) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Edit</a>@endcan
            </div>
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="mb-6 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            @can('submit', $booking)
                <form method="POST" action="{{ route('bookings.submit', $booking) }}">@csrf<button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Submit for approval</button></form>
            @endcan
            @can('verify', $booking)
                <form method="POST" action="{{ route('bookings.verify', $booking) }}">@csrf<button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Verify</button></form>
            @endcan
            @can('approve', $booking)
                <form method="POST" action="{{ route('bookings.approve', $booking) }}">@csrf<button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button></form>
            @endcan
            @can('reject', $booking)
                <form method="POST" action="{{ route('bookings.reject', $booking) }}">@csrf<button class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Reject</button></form>
            @endcan
            @can('hold', $booking)
                <form method="POST" action="{{ route('bookings.hold', $booking) }}">@csrf<button class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">Hold</button></form>
            @endcan
            @can('unlock', $booking)
                <form method="POST" action="{{ route('bookings.unlock', $booking) }}">@csrf<button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unlock</button></form>
            @endcan
            @can('receivePayment', $booking)
                <button type="button" @click="paying = ! paying" class="rounded-xl border border-blue-300 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">Receive payment</button>
            @endcan
            @can('updateDispatchStatus', $booking)
                <form method="POST" action="{{ route('bookings.complete-dispatch', $booking) }}" onsubmit="return confirm('Mark dispatch complete? This converts the stock reservation into an actual stock issue.')">@csrf<button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Mark dispatch complete</button></form>
            @endcan
            @can('delete', $booking)
                <form method="POST" action="{{ route('bookings.destroy', $booking) }}" class="ml-auto" onsubmit="return confirm('Delete this booking?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 hover:text-rose-800">Delete booking</button></form>
            @endcan
        </div>

        @can('receivePayment', $booking)
            <div x-show="paying" x-cloak class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-4">
                <form method="POST" action="{{ route('bookings.receive-payment', $booking) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div><label class="text-xs font-semibold text-slate-600">Amount (₹) — balance ₹{{ number_format($booking->balance_amount, 2) }}</label><input type="number" step="0.01" min="0.01" name="amount" required class="mt-1 block w-40 rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></div>
                    <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Record payment</button>
                </form>
            </div>
        @endcan

        <div class="mb-6 flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            @foreach(['details' => 'Details', 'stock' => 'Stock Reservation', 'timeline' => 'Timeline', 'documents' => 'Documents', 'history' => 'Audit history'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-lg px-4 py-2 text-sm font-semibold transition">{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'details'" class="grid gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Booking</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Variety</dt><dd class="font-medium text-slate-800">{{ $booking->variety }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Booking date</dt><dd class="font-medium text-slate-800">{{ $booking->booking_date->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Plant quantity</dt><dd class="font-medium text-slate-800">{{ number_format($booking->plant_qty) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Plant rate</dt><dd class="font-medium text-slate-800">₹{{ number_format($booking->plant_rate, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Marketing contact</dt><dd class="font-medium text-slate-800">{{ $booking->marketingUser?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Accounts contact</dt><dd class="font-medium text-slate-800">{{ $booking->accountsUser?->name ?? '—' }}</dd></div>
                </dl>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Amount</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Booking amount</dt><dd class="font-medium text-slate-800">₹{{ number_format($booking->booking_amount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="font-medium text-slate-800">₹{{ number_format($booking->discount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Advance received</dt><dd class="font-medium text-slate-800">₹{{ number_format($booking->advance_amount, 2) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-3"><dt class="font-semibold text-slate-600">Balance</dt><dd class="font-bold text-slate-900">₹{{ number_format($booking->balance_amount, 2) }}</dd></div>
                </dl>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Status</h2>
                <dl class="grid gap-4 sm:grid-cols-4">
                    <div><dt class="text-xs text-slate-500">Approval</dt><dd class="mt-1"><x-booking-status-badge :status="$booking->approval_status" /></dd></div>
                    <div><dt class="text-xs text-slate-500">Payment</dt><dd class="mt-1"><x-booking-status-badge :status="$booking->payment_status" /></dd></div>
                    <div><dt class="text-xs text-slate-500">Dispatch</dt><dd class="mt-1"><x-booking-status-badge :status="$booking->dispatch_status" /></dd></div>
                    <div><dt class="text-xs text-slate-500">Invoice</dt><dd class="mt-1"><x-booking-status-badge :status="$booking->invoice_status" /></dd></div>
                </dl>
            </div>
            @if($approvalLevels->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Approval levels</h2>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        @foreach($approvalLevels as $level)
                            <div class="rounded-xl border border-slate-100 p-4">
                                <div class="flex items-center justify-between">
                                    <dt class="text-xs font-semibold uppercase text-slate-500">{{ $level->approval_level == 1 ? 'Level 1 — Verification' : 'Level 2 — Approval' }}</dt>
                                    <dd><x-booking-status-badge :status="$level->status" /></dd>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">
                                    @if($level->approved_by) Signed off by {{ $level->approvedBy?->name }} · {{ $level->approved_at?->format('d M Y, h:i A') }} @endif
                                    @if($level->rejected_by) Rejected by {{ $level->rejectedBy?->name }} · {{ $level->rejected_at?->format('d M Y, h:i A') }} @endif
                                    @if($level->hold_by) Held by {{ $level->holdBy?->name }} · {{ $level->hold_at?->format('d M Y, h:i A') }} @endif
                                    @if($level->unlock_by) Unlocked by {{ $level->unlockBy?->name }} · {{ $level->unlock_at?->format('d M Y, h:i A') }} @endif
                                </p>
                                @if($level->remarks)<p class="mt-1 text-xs italic text-slate-400">"{{ $level->remarks }}"</p>@endif
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif
            @if($booking->remarks)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-slate-400">Remarks</h2>
                    <p class="text-sm text-slate-700">{{ $booking->remarks }}</p>
                </div>
            @endif
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Record</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between"><dt class="text-slate-500">Created by</dt><dd class="font-medium text-slate-800">{{ $booking->createdBy?->name ?? '—' }} · {{ $booking->created_at?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Last updated by</dt><dd class="font-medium text-slate-800">{{ $booking->updatedBy?->name ?? '—' }} · {{ $booking->updated_at?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Approved by</dt><dd class="font-medium text-slate-800">{{ $booking->approvedBy?->name ?? '—' }}</dd></div>
                </dl>
            </div>
        </div>

        <div x-show="tab === 'stock'" class="grid gap-6 sm:grid-cols-2">
            @if($reservation)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Reservation</h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd><x-booking-status-badge :status="$reservation->status" /></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Variety</dt><dd class="font-medium text-slate-800">{{ $reservation->variety }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Reserved qty</dt><dd class="font-medium text-slate-800">{{ number_format($reservation->reserved_qty) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Released qty</dt><dd class="font-medium text-slate-800">{{ number_format($reservation->released_qty) }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Reservation timeline</h2>
                    <div class="space-y-4">
                        @forelse($reservationActivity as $entry)
                            <div class="relative pb-4 pl-6 last:pb-0">
                                <span class="absolute left-0 top-1 grid h-4 w-4 place-items-center rounded-full bg-blue-600"><span class="h-1.5 w-1.5 rounded-full bg-white"></span></span>
                                @unless($loop->last)<span class="absolute left-[7px] top-5 h-full w-px bg-slate-200"></span>@endunless
                                <p class="text-xs font-semibold text-slate-800">{{ $entry->remarks ?? ucfirst($entry->action) }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $entry->created_at->format('d M Y, h:i A') }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No reservation activity yet.</p>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center sm:col-span-2">
                    <p class="text-sm text-slate-500">No stock reservation exists for this booking.</p>
                </div>
            @endif
        </div>

        <div x-show="tab === 'timeline'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @forelse($activity as $entry)
                <div class="relative pb-8 pl-8 last:pb-0">
                    <span class="absolute left-0 top-1 grid h-5 w-5 place-items-center rounded-full bg-blue-600"><span class="h-2 w-2 rounded-full bg-white"></span></span>
                    @unless($loop->last)<span class="absolute left-[9px] top-6 h-full w-px bg-slate-200"></span>@endunless
                    <p class="text-sm font-semibold capitalize text-slate-800">Booking {{ $entry->action }}{{ $entry->remarks ? ' — '.$entry->remarks : '' }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $entry->created_at->format('d M Y, h:i A') }} · by {{ $entry->user?->name ?? 'System' }}</p>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-slate-500">No timeline events yet.</p>
            @endforelse
        </div>

        <div x-show="tab === 'documents'" class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <p class="text-lg font-semibold text-slate-700">Booking documents</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Challans, invoices and other supporting documents will appear here once those modules are built.</p>
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
                                        <p><span class="font-semibold text-slate-600">{{ $field }}:</span> <span class="text-rose-600 line-through">{{ $entry->old_values[$field] ?? '—' }}</span> → <span class="text-emerald-700">{{ $newValue ?? '—' }}</span></p>
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
