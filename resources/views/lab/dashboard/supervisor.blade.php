<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">LABORATORY OPERATIONS</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Lab Ops Dashboard</h1>
                <p class="mt-2 text-sm text-slate-500">{{ now()->format('l, d M Y') }}</p>
            </div>
            <div class="flex items-center gap-4">
                @can('viewAny', \App\Models\LabDailyChecklist::class)
                    <a href="{{ route('lab-reports.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">View reports →</a>
                @endcan
                @can('viewAny', \App\Models\ReportTemplate::class)
                    <a href="{{ route('report-templates.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">Custom report templates →</a>
                @endcan
            </div>
        </div>

        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Today's compliance</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $dailyCompliance }}%</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">This week's compliance</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $weeklyCompliance }}%</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pending approvals</p>
                <p class="mt-2 text-2xl font-bold text-amber-600">{{ $pendingCounts->sum() }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Open contamination</p>
                <p class="mt-2 text-2xl font-bold {{ $criticalContamination > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $openContamination }}</p>
                @if($criticalContamination > 0)<p class="mt-1 text-xs font-semibold text-rose-600">{{ $criticalContamination }} critical</p>@endif
            </div>
        </div>

        <div class="mb-8 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Equipment under maintenance</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $equipmentUnderMaintenance }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Overdue maintenance</p>
                <p class="mt-2 text-2xl font-bold {{ $overdueMaintenance > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $overdueMaintenance }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Low stock media items</p>
                <p class="mt-2 text-2xl font-bold {{ $lowStockMedia > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $lowStockMedia }}</p>
            </div>
        </div>

        @php
            $moduleRoutes = [
                'lab-checklists' => ['show' => 'lab-checklists.show', 'label' => 'Checklist', 'field' => 'checklist_no'],
                'lab-maintenance' => ['show' => 'lab-maintenance.show', 'label' => 'Maintenance', 'field' => 'log_no'],
                'lab-media' => ['show' => 'lab-media.show', 'label' => 'Media', 'field' => 'verification_no'],
                'lab-chemicals' => ['show' => 'lab-chemicals.show', 'label' => 'Chemical', 'field' => 'usage_no'],
                'lab-contamination' => ['show' => 'lab-contamination.show', 'label' => 'Contamination', 'field' => 'contamination_no'],
            ];
        @endphp

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4"><h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Pending approvals</h2></div>
            <div class="divide-y divide-slate-100">
                @forelse($pendingRecords as $item)
                    @php($meta = $moduleRoutes[$item['module']])
                    <a href="{{ route($meta['show'], $item['record']) }}" class="flex items-center justify-between px-6 py-4 hover:bg-slate-50">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ $meta['label'] }} · {{ $item['record']->{$meta['field']} }}</p>
                            <p class="text-xs text-slate-500">Submitted {{ $item['record']->created_at->diffForHumans() }}</p>
                        </div>
                        <span class="text-sm font-semibold text-blue-600">Review →</span>
                    </a>
                @empty
                    <p class="px-6 py-12 text-center text-sm text-slate-500">Nothing pending approval right now.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
