<x-app-layout>
    <div class="mx-auto max-w-5xl" x-data="{ tab: 'profile' }">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('dealers.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to dealers</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $dealer->dealer_name }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $dealer->firm_name }} · {{ $dealer->dealer_code }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span @class(['inline-flex rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-emerald-100 text-emerald-700' => $dealer->status, 'bg-slate-100 text-slate-600' => ! $dealer->status])>{{ $dealer->status ? 'Active' : 'Inactive' }}</span>
                <x-rating-control type="dealer" :model="$dealer" :can-edit="auth()->user()->hasRole('super-admin')" />
                @can('update', $dealer)<a href="{{ route('dealers.edit', $dealer) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Edit dealer</a>@endcan
            </div>
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="mb-6 flex gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            <button type="button" @click="tab = 'profile'" :class="tab === 'profile' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">Profile</button>
            <button type="button" @click="tab = 'ledger'" :class="tab === 'ledger' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">Ledger</button>
            <button type="button" @click="tab = 'documents'" :class="tab === 'documents' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">Documents</button>
            <button type="button" @click="tab = 'ratings'" :class="tab === 'ratings' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">Ratings</button>
            <button type="button" @click="tab = 'history'" :class="tab === 'history' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">Audit history</button>
        </div>

        <div x-show="tab === 'profile'" class="grid gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Contact</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Mobile</dt><dd class="font-medium text-slate-800">{{ $dealer->mobile }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">WhatsApp</dt><dd class="font-medium text-slate-800">{{ $dealer->whatsapp ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd class="font-medium text-slate-800">{{ $dealer->email ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Marketing contact</dt><dd class="font-medium text-slate-800">{{ $dealer->assignment?->marketingUser?->name ?? '—' }}</dd></div>
                </dl>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Compliance & credit</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">GST number</dt><dd class="font-medium text-slate-800">{{ $dealer->gst_number ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">PAN number</dt><dd class="font-medium text-slate-800">{{ $dealer->pan_number ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Agreement date</dt><dd class="font-medium text-slate-800">{{ $dealer->agreement_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Credit limit</dt><dd class="font-medium text-slate-800">₹{{ number_format($dealer->credit_limit, 2) }}</dd></div>
                </dl>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Location</h2>
                <p class="text-sm text-slate-700">{{ $dealer->address ?? '—' }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ collect([$dealer->village?->name, $dealer->taluka?->name, $dealer->district?->name, $dealer->state?->name, $dealer->pin_code])->filter()->implode(', ') ?: '—' }}</p>
            </div>
            @if($dealer->remarks)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-slate-400">Remarks</h2>
                    <p class="text-sm text-slate-700">{{ $dealer->remarks }}</p>
                </div>
            @endif
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Record</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between"><dt class="text-slate-500">Created by</dt><dd class="font-medium text-slate-800">{{ $dealer->createdBy?->name ?? '—' }} · {{ $dealer->created_at?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Last updated by</dt><dd class="font-medium text-slate-800">{{ $dealer->updatedBy?->name ?? '—' }} · {{ $dealer->updated_at?->format('d M Y') }}</dd></div>
                </dl>
            </div>
        </div>

        <div x-show="tab === 'ledger'" class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <p class="text-lg font-semibold text-slate-700">Dealer ledger</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Booking, payment and outstanding-balance history for this dealer will appear here once the Ledger module is built.</p>
        </div>

        <div x-show="tab === 'documents'" class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <p class="text-lg font-semibold text-slate-700">Dealer documents</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Agreement copies, GST/PAN proofs and other uploaded documents will appear here once the Documents module is built.</p>
        </div>

        <div x-show="tab === 'ratings'" x-cloak>
            <x-rating-history-list :history="$ratingHistory" />
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
                    </div>
                @empty
                    <p class="px-6 py-12 text-center text-sm text-slate-500">No activity recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
