<x-app-layout>
    <div class="mx-auto max-w-5xl" x-data="{ tab: 'details' }">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('lab-contamination.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to contamination records</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $labContamination->contamination_no }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $labContamination->contamination_date?->format('d M Y') }}@if($labContamination->culture_batch_number) · Batch {{ $labContamination->culture_batch_number }}@endif</p>
            </div>
            <div class="flex items-center gap-3">
                <x-lab-status-badge :status="$labContamination->status" class="!px-3 !py-1.5 !text-xs" />
                @can('update', $labContamination)<a href="{{ route('lab-contamination.edit', $labContamination) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Edit</a>@endcan
            </div>
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="mb-6 flex gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            <button type="button" @click="tab = 'details'" :class="tab === 'details' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">Details</button>
            <button type="button" @click="tab = 'approval'" :class="tab === 'approval' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">Approval</button>
            <button type="button" @click="tab = 'history'" :class="tab === 'history' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">Audit history</button>
        </div>

        <div x-show="tab === 'details'" class="grid gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Incident detail</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between"><dt class="text-slate-500">Reported by</dt><dd class="font-medium text-slate-800">{{ $labContamination->reportedBy?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Type</dt><dd class="font-medium capitalize text-slate-800">{{ $labContamination->contamination_type }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Severity</dt><dd class="font-medium capitalize text-slate-800">{{ $labContamination->severity }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Affected quantity</dt><dd class="font-medium text-slate-800">{{ $labContamination->affected_qty ?? '—' }}</dd></div>
                </dl>
                @if($labContamination->root_cause)<p class="mt-4 text-sm text-slate-700"><span class="font-semibold">Root cause:</span> {{ $labContamination->root_cause }}</p>@endif
                @if($labContamination->corrective_action)<p class="mt-2 text-sm text-slate-700"><span class="font-semibold">Corrective action:</span> {{ $labContamination->corrective_action }}</p>@endif
                @if($labContamination->remarks)<p class="mt-2 text-sm text-slate-500">{{ $labContamination->remarks }}</p>@endif
            </div>

            @if($labContamination->photo)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Photo evidence</h2>
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($labContamination->photo) }}" target="_blank"><img src="{{ \Illuminate\Support\Facades\Storage::url($labContamination->photo) }}" class="max-h-72 rounded-xl border border-slate-200 object-cover"></a>
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Record</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between"><dt class="text-slate-500">Created by</dt><dd class="font-medium text-slate-800">{{ $labContamination->createdBy?->name ?? '—' }} · {{ $labContamination->created_at?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Last updated by</dt><dd class="font-medium text-slate-800">{{ $labContamination->updatedBy?->name ?? '—' }} · {{ $labContamination->updated_at?->format('d M Y') }}</dd></div>
                </dl>
            </div>
        </div>

        <div x-show="tab === 'approval'" x-cloak class="space-y-6">
            @can('submit', $labContamination)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm text-slate-600">This record is still a draft. Submit it for supervisor approval once it's complete.</p>
                    <form method="POST" action="{{ route('lab-contamination.submit', $labContamination) }}" class="mt-4">@csrf<button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Submit for approval</button></form>
                </div>
            @endcan

            @can('approve', $labContamination)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Supervisor decision</h2>
                    <form method="POST" action="{{ route('lab-contamination.approve', $labContamination) }}" class="space-y-4" onsubmit="return window.arcadiaCheckSignaturePresent(this, 'contamination-approve-pad_input')">
                        @csrf
                        <div><label class="text-sm font-semibold text-slate-700">Remarks (optional)</label><textarea name="remarks" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea></div>
                        <x-lab-signature-pad id="contamination-approve-pad" />
                        @error('signature_data')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                        <div class="flex justify-end"><button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Approve</button></div>
                    </form>
                    <form method="POST" action="{{ route('lab-contamination.reject', $labContamination) }}" class="mt-4 space-y-4 border-t border-slate-100 pt-4">
                        @csrf
                        <div><label class="text-sm font-semibold text-slate-700">Rejection reason (required)</label><textarea name="remarks" rows="2" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea></div>
                        <div class="flex justify-end"><button class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-700">Reject</button></div>
                    </form>
                </div>
            @endcan

            @can('unlock', $labContamination)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm text-slate-600">Reopen this record for editing.</p>
                    <form method="POST" action="{{ route('lab-contamination.unlock', $labContamination) }}" class="mt-4">@csrf<button class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-600">Unlock</button></form>
                </div>
            @endcan

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="divide-y divide-slate-100">
                    @forelse($approvalLevels as $level)
                        <div class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold capitalize text-slate-800">{{ $level->status }}</span>
                                <span class="text-xs text-slate-400">{{ $level->updated_at?->format('d M Y, h:i A') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">by {{ $level->approvedBy?->name ?? $level->rejectedBy?->name ?? $level->unlockBy?->name ?? '—' }}@if($level->remarks) · {{ $level->remarks }}@endif</p>
                            @if($level->signature)<a href="{{ \Illuminate\Support\Facades\Storage::url($level->signature) }}" target="_blank"><img src="{{ \Illuminate\Support\Facades\Storage::url($level->signature) }}" class="mt-2 h-16 rounded-lg border border-slate-200 bg-white"></a>@endif
                        </div>
                    @empty
                        <p class="px-6 py-12 text-center text-sm text-slate-500">No approval decisions recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div x-show="tab === 'history'" x-cloak class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse($activity as $entry)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold capitalize text-slate-800">{{ $entry->action }}</span>
                            <span class="text-xs text-slate-400">{{ $entry->created_at->format('d M Y, h:i A') }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">by {{ $entry->user?->name ?? 'System' }}@if($entry->remarks) · {{ $entry->remarks }}@endif</p>
                    </div>
                @empty
                    <p class="px-6 py-12 text-center text-sm text-slate-500">No activity recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
