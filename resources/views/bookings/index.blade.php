<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">SALES</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Bookings</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $bookings->total() }} booking{{ $bookings->total() === 1 ? '' : 's' }} on record.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('bookings.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16" /></svg>Export CSV</a>
                @can('create', \App\Models\Booking::class)
                    <a href="{{ route('bookings.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 4v16m8-8H4" /></svg>New booking</a>
                @endcan
            </div>
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif
        @error('approval_status')<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $message }}</div>@enderror

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <input name="search" value="{{ request('search') }}" placeholder="Search booking no, dealer, farmer..." class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:max-w-xs">

                    <select name="dealer" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All dealers</option>
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected(request('dealer') == $dealer->id)>{{ $dealer->dealer_name }}</option>
                        @endforeach
                    </select>

                    <select name="variety" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All varieties</option>
                        @foreach($varieties as $variety)<option value="{{ $variety }}" @selected(request('variety') === $variety)>{{ $variety }}</option>@endforeach
                    </select>

                    <select name="approval_status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Any approval status</option>
                        @foreach($approvalStatuses as $status)<option value="{{ $status }}" @selected(request('approval_status') === $status)>{{ ucfirst($status) }}</option>@endforeach
                    </select>

                    <select name="payment_status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Any payment status</option>
                        @foreach($paymentStatuses as $status)<option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ ucfirst($status) }}</option>@endforeach
                    </select>

                    <select name="dispatch_status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Any dispatch status</option>
                        @foreach($dispatchStatuses as $status)<option value="{{ $status }}" @selected(request('dispatch_status') === $status)>{{ ucfirst($status) }}</option>@endforeach
                    </select>

                    <select name="invoice_status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Any invoice status</option>
                        @foreach($invoiceStatuses as $status)<option value="{{ $status }}" @selected(request('invoice_status') === $status)>{{ ucfirst($status) }}</option>@endforeach
                    </select>

                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" title="From date">
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" title="To date">

                    @if ($trashed)<input type="hidden" name="trashed" value="1">@endif

                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    <a href="{{ route('bookings.index', $trashed ? ['trashed' => 1] : []) }}" class="rounded-xl px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-100">Clear</a>

                    <div class="sm:ml-auto">
                        @can('viewAny', \App\Models\Booking::class)
                            @if ($trashed)
                                <a href="{{ route('bookings.index') }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50">← Back to active bookings</a>
                            @else
                                <a href="{{ route('bookings.index', ['trashed' => 1]) }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100">View deleted bookings</a>
                            @endif
                        @endcan
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Booking</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dealer / Farmer</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Approval</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Payment</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($bookings as $booking)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-900">{{ $booking->booking_no }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $booking->variety }} · {{ $booking->booking_date->format('d M Y') }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $booking->dealer?->dealer_name }}<br><span class="text-xs text-slate-400">{{ $booking->farmer?->farmer_name }}</span></td>
                                <td class="px-6 py-4 text-right text-sm text-slate-600">₹{{ number_format($booking->booking_amount, 2) }}<br><span class="text-xs text-slate-400">Bal ₹{{ number_format($booking->balance_amount, 2) }}</span></td>
                                <td class="px-6 py-4"><x-booking-status-badge :status="$booking->approval_status" /></td>
                                <td class="px-6 py-4"><x-booking-status-badge :status="$booking->payment_status" /></td>
                                <td class="px-6 py-4 text-right">
                                    @if ($trashed)
                                        @can('restore', $booking)
                                            <form method="POST" action="{{ route('bookings.restore', $booking) }}" class="inline">@csrf<button class="text-sm font-semibold text-emerald-600 hover:text-emerald-800">Restore</button></form>
                                        @endcan
                                    @else
                                        @can('view', $booking)<a href="{{ route('bookings.show', $booking) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">View</a>@endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">No bookings found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $bookings->links() }}</div>
        </div>
    </div>
</x-app-layout>
