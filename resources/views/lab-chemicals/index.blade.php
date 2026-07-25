<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">LABORATORY OPERATIONS</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Chemical Usage</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $usages->total() }} entr{{ $usages->total() === 1 ? 'y' : 'ies' }} on record.</p>
            </div>
            @can('create', \App\Models\LabChemicalUsage::class)
                <a href="{{ route('lab-chemicals.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 4v16m8-8H4" /></svg>New entry</a>
            @endcan
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <input name="search" value="{{ request('search') }}" placeholder="Search usage no, chemical..." class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:max-w-xs">
                    <select name="status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Any status</option>
                        @foreach(\App\Models\LabChemicalUsage::STATUSES as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <input type="date" name="to_date" value="{{ request('to_date') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @if ($trashed)<input type="hidden" name="trashed" value="1">@endif
                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    @if(request('search') || request('status') || request('from_date') || request('to_date'))<a href="{{ route('lab-chemicals.index', $trashed ? ['trashed' => 1] : []) }}" class="rounded-xl px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-100">Clear</a>@endif
                    <div class="flex items-center gap-3 sm:ml-auto">
                        @can('viewAny', \App\Models\LabChemicalUsage::class)
                            <a href="{{ route('lab-chemicals.export', request()->query()) }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Export CSV</a>
                            @if ($trashed)
                                <a href="{{ route('lab-chemicals.index') }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50">← Back to active</a>
                            @else
                                <a href="{{ route('lab-chemicals.index', ['trashed' => 1]) }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100">View deleted</a>
                            @endif
                        @endcan
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Usage</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Chemical</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($usages as $usage)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 font-semibold text-slate-900">{{ $usage->usage_no }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $usage->chemical_name }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $usage->usage_date?->format('d M Y') }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $usage->quantity_used }} {{ $usage->unit }}</td>
                                <td class="px-6 py-4"><x-lab-status-badge :status="$usage->status" /></td>
                                <td class="px-6 py-4 text-right">
                                    @if ($trashed)
                                        @can('restore', $usage)
                                            <form method="POST" action="{{ route('lab-chemicals.restore', $usage) }}" class="inline">@csrf<button class="text-sm font-semibold text-emerald-600 hover:text-emerald-800">Restore</button></form>
                                        @endcan
                                    @else
                                        @can('view', $usage)<a href="{{ route('lab-chemicals.show', $usage) }}" class="mr-3 text-sm font-semibold text-slate-600 hover:text-slate-800">View</a>@endcan
                                        @can('update', $usage)<a href="{{ route('lab-chemicals.edit', $usage) }}" class="mr-3 text-sm font-semibold text-blue-600 hover:text-blue-800">Edit</a>@endcan
                                        @can('delete', $usage)<form method="POST" action="{{ route('lab-chemicals.destroy', $usage) }}" class="inline" onsubmit="return confirm('Delete this entry?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 hover:text-rose-800">Delete</button></form>@endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">No chemical usage entries found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $usages->links() }}</div>
        </div>
    </div>
</x-app-layout>
