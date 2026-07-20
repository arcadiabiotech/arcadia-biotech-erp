<x-app-layout>
    <div class="mx-auto max-w-5xl" x-data="{ tab: 'profile' }">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('farmers.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to farmers</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $farmer->farmer_name }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $farmer->farmer_code }} · {{ $farmer->dealer?->dealer_name ?? 'No dealer' }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span @class(['inline-flex rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-emerald-100 text-emerald-700' => $farmer->status, 'bg-slate-100 text-slate-600' => ! $farmer->status])>{{ $farmer->status ? 'Active' : 'Inactive' }}</span>
                @can('update', $farmer)<a href="{{ route('farmers.edit', $farmer) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Edit farmer</a>@endcan
            </div>
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="mb-6 flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            @foreach(['profile' => 'Profile', 'timeline' => 'Timeline', 'documents' => 'Documents', 'plantation' => 'Plantation', 'booking' => 'Booking', 'ledger' => 'Ledger', 'history' => 'Audit history'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-lg px-4 py-2 text-sm font-semibold transition">{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'profile'" class="grid gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Contact</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Father's name</dt><dd class="font-medium text-slate-800">{{ $farmer->father_name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Mobile</dt><dd class="font-medium text-slate-800">{{ $farmer->mobile }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Alternate mobile</dt><dd class="font-medium text-slate-800">{{ $farmer->alternate_mobile ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Aadhaar</dt><dd class="font-medium text-slate-800">{{ $farmer->aadhaar_no ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Dealer</dt><dd class="font-medium text-slate-800">{{ $farmer->dealer?->dealer_name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Marketing contact</dt><dd class="font-medium text-slate-800">{{ $farmer->dealer?->assignment?->marketingUser?->name ?? '—' }}</dd></div>
                </dl>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Farm details</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Farm area</dt><dd class="font-medium text-slate-800">{{ $farmer->farm_area ? number_format($farmer->farm_area, 2).' acres' : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Soil type</dt><dd class="font-medium text-slate-800">{{ $farmer->soil_type ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Irrigation type</dt><dd class="font-medium text-slate-800">{{ $farmer->irrigation_type ?? '—' }}</dd></div>
                </dl>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Location</h2>
                <p class="text-sm text-slate-700">{{ $farmer->address ?? '—' }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ collect([$farmer->village?->name, $farmer->taluka?->name, $farmer->district?->name, $farmer->state?->name, $farmer->pincode])->filter()->implode(', ') ?: '—' }}</p>
            </div>
            @if($farmer->remarks)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-slate-400">Remarks</h2>
                    <p class="text-sm text-slate-700">{{ $farmer->remarks }}</p>
                </div>
            @endif
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Record</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between"><dt class="text-slate-500">Created by</dt><dd class="font-medium text-slate-800">{{ $farmer->createdBy?->name ?? '—' }} · {{ $farmer->created_at?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Last updated by</dt><dd class="font-medium text-slate-800">{{ $farmer->updatedBy?->name ?? '—' }} · {{ $farmer->updated_at?->format('d M Y') }}</dd></div>
                </dl>
            </div>
        </div>

        <div x-show="tab === 'timeline'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @forelse($activity as $entry)
                <div class="relative pb-8 pl-8 last:pb-0">
                    <span class="absolute left-0 top-1 grid h-5 w-5 place-items-center rounded-full bg-blue-600"><span class="h-2 w-2 rounded-full bg-white"></span></span>
                    @unless($loop->last)<span class="absolute left-[9px] top-6 h-full w-px bg-slate-200"></span>@endunless
                    <p class="text-sm font-semibold capitalize text-slate-800">Farmer record {{ $entry->action }}{{ $entry->remarks ? ' — '.$entry->remarks : '' }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $entry->created_at->format('d M Y, h:i A') }} · by {{ $entry->user?->name ?? 'System' }}</p>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-slate-500">No timeline events yet.</p>
            @endforelse
        </div>

        <div x-show="tab === 'documents'" class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <p class="text-lg font-semibold text-slate-700">Farmer documents</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Aadhaar copies, land records and other uploaded documents will appear here once the Documents module is built.</p>
        </div>

        <div x-show="tab === 'plantation'" class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <p class="text-lg font-semibold text-slate-700">Plantation records</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Plantation batches, tissue-culture variety and planting dates for this farmer will appear here once the Plantation module is built.</p>
        </div>

        <div x-show="tab === 'booking'" class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <p class="text-lg font-semibold text-slate-700">Bookings</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">This farmer's plant bookings will appear here once the Booking module is built.</p>
        </div>

        <div x-show="tab === 'ledger'" class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <p class="text-lg font-semibold text-slate-700">Farmer ledger</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Payment and outstanding-balance history for this farmer will appear here once the Ledger module is built.</p>
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
                        @if($entry->action === 'update' && $entry->old_values && $entry->new_values)
                            <div class="mt-2 space-y-1 rounded-lg bg-slate-50 p-3 text-xs">
                                @foreach(array_diff_assoc(array_intersect_key($entry->new_values, $entry->old_values), $entry->old_values) as $field => $newValue)
                                    <p><span class="font-semibold text-slate-600">{{ $field }}:</span> <span class="text-rose-600 line-through">{{ $entry->old_values[$field] ?? '—' }}</span> → <span class="text-emerald-700">{{ $newValue ?? '—' }}</span></p>
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
