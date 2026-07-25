<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8">
            <p class="text-sm font-semibold text-blue-600">LABORATORY OPERATIONS</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">My Lab Dashboard</h1>
            <p class="mt-2 text-sm text-slate-500">{{ now()->format('l, d M Y') }}</p>
        </div>

        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Today's checklist</p>
                @if($todayChecklist)
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $todayChecklist->compliance_score }}%</p>
                    <div class="mt-1"><x-lab-status-badge :status="$todayChecklist->status" /></div>
                @else
                    <p class="mt-2 text-lg font-bold text-rose-600">Not submitted</p>
                    <a href="{{ route('lab-checklists.create') }}" class="mt-1 inline-block text-xs font-semibold text-blue-600 hover:underline">Submit now →</a>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">My open items</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $ownPending->sum() }}</p>
                <p class="mt-1 text-xs text-slate-500">Draft or pending across all logs</p>
            </div>
        </div>

        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <a href="{{ route('lab-checklists.create') }}" class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm transition hover:border-blue-300 hover:shadow-md">
                <p class="text-sm font-semibold text-slate-800">Daily Checklist</p>
                <p class="mt-1 text-xs text-slate-500">{{ $ownPending['lab-checklists'] ?? 0 }} open</p>
            </a>
            <a href="{{ route('lab-maintenance.create') }}" class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm transition hover:border-blue-300 hover:shadow-md">
                <p class="text-sm font-semibold text-slate-800">Maintenance Log</p>
                <p class="mt-1 text-xs text-slate-500">{{ $ownPending['lab-maintenance'] ?? 0 }} open</p>
            </a>
            <a href="{{ route('lab-media.create') }}" class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm transition hover:border-blue-300 hover:shadow-md">
                <p class="text-sm font-semibold text-slate-800">Media Verification</p>
                <p class="mt-1 text-xs text-slate-500">{{ $ownPending['lab-media'] ?? 0 }} open</p>
            </a>
            <a href="{{ route('lab-chemicals.create') }}" class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm transition hover:border-blue-300 hover:shadow-md">
                <p class="text-sm font-semibold text-slate-800">Chemical Usage</p>
                <p class="mt-1 text-xs text-slate-500">{{ $ownPending['lab-chemicals'] ?? 0 }} open</p>
            </a>
            <a href="{{ route('lab-contamination.create') }}" class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm transition hover:border-blue-300 hover:shadow-md">
                <p class="text-sm font-semibold text-slate-800">Contamination</p>
                <p class="mt-1 text-xs text-slate-500">{{ $ownPending['lab-contamination'] ?? 0 }} open</p>
            </a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4"><h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">My recent checklists</h2></div>
            <div class="divide-y divide-slate-100">
                @forelse($recent as $checklist)
                    <a href="{{ route('lab-checklists.show', $checklist) }}" class="flex items-center justify-between px-6 py-4 hover:bg-slate-50">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ $checklist->checklist_no }}</p>
                            <p class="text-xs text-slate-500">{{ $checklist->checklist_date?->format('d M Y') }} · {{ $checklist->compliance_score }}% complete</p>
                        </div>
                        <x-lab-status-badge :status="$checklist->status" />
                    </a>
                @empty
                    <p class="px-6 py-12 text-center text-sm text-slate-500">No checklists submitted yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
