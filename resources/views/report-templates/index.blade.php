<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">LABORATORY OPERATIONS</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Custom Report Templates</h1>
                <p class="mt-2 text-sm text-slate-500">Saved module + date-range selections you can run at any time.</p>
            </div>
            @can('create', \App\Models\ReportTemplate::class)
                <a href="{{ route('report-templates.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 4v16m8-8H4" /></svg>Create Report</a>
            @endcan
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Modules</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Period</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Created by</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($templates as $template)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 font-semibold text-slate-900">{{ $template->name }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ collect($template->modules)->map(fn ($m) => \App\Models\ReportTemplate::MODULES[$m] ?? $m)->join(', ') }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ ucfirst($template->date_range_type) }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $template->createdBy?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('report-templates.run', $template) }}" class="mr-3 text-sm font-semibold text-blue-600 hover:text-blue-800">Run →</a>
                                    @can('update', $template)<a href="{{ route('report-templates.edit', $template) }}" class="mr-3 text-sm font-semibold text-slate-600 hover:text-slate-800">Edit</a>@endcan
                                    @can('delete', $template)<form method="POST" action="{{ route('report-templates.destroy', $template) }}" class="inline" onsubmit="return confirm('Delete this report template?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 hover:text-rose-800">Delete</button></form>@endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">No custom report templates yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
