<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('lab-reports.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to reports</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ ucfirst($period) }} Report</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $report['label'] }}</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('lab-reports.csv', ['period' => $period, 'date' => request('date')]) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Export CSV</a>
                <a href="{{ route('lab-reports.pdf', ['period' => $period, 'date' => request('date')]) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Download PDF</a>
            </div>
        </div>

        <form class="mb-6 flex items-center gap-3">
            <input type="hidden" name="period" value="{{ $period }}">
            <label class="text-sm font-semibold text-slate-700">Jump to date</label>
            <input type="date" name="date" value="{{ request('date', now()->toDateString()) }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Go</button>
        </form>

        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Checklist compliance</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $report['compliance'] }}%</p>
                <p class="mt-1 text-xs text-slate-500">{{ $report['checklistsApproved'] }} approved of {{ $report['checklistsSubmitted'] }} submitted</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Overdue maintenance</p>
                <p class="mt-2 text-2xl font-bold {{ $report['overdueMaintenance'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $report['overdueMaintenance'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Critical contamination</p>
                <p class="mt-2 text-2xl font-bold {{ $report['criticalContamination'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $report['criticalContamination'] }}</p>
            </div>
        </div>

        <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Checklist item compliance</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                @foreach(\App\Models\LabDailyChecklist::CHECKLIST_ITEMS as $key => $meta)
                    <div class="flex justify-between"><dt class="text-slate-500">{{ $meta['label'] }}</dt><dd class="font-medium text-slate-800">{{ $report['itemCompliance'][$key] }}%</dd></div>
                @endforeach
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Operational summary</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="flex justify-between"><dt class="text-slate-500">Equipment maintenance logs</dt><dd class="font-medium text-slate-800">{{ $report['maintenanceCount'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Media verifications</dt><dd class="font-medium text-slate-800">{{ $report['mediaCount'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Low stock media items</dt><dd class="font-medium text-slate-800">{{ $report['lowStockMedia'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Chemical usage entries</dt><dd class="font-medium text-slate-800">{{ $report['chemicalCount'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Contamination incidents</dt><dd class="font-medium text-slate-800">{{ $report['contaminationCount'] }}</dd></div>
            </dl>
        </div>
    </div>
</x-app-layout>
