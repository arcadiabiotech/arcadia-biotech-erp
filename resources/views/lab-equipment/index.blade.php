<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">LABORATORY OPERATIONS</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Lab Equipment</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $equipment->total() }} item{{ $equipment->total() === 1 ? '' : 's' }} registered.</p>
            </div>
            @can('create', \App\Models\LabEquipment::class)
                <a href="{{ route('lab-equipment.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 4v16m8-8H4" /></svg>Add equipment</a>
            @endcan
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <input name="search" value="{{ request('search') }}" placeholder="Search code, name, location..." class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:max-w-xs">
                    <select name="status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Any status</option>
                        @foreach(\App\Models\LabEquipment::STATUSES as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                    @if ($trashed)<input type="hidden" name="trashed" value="1">@endif
                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    @if(request('search') || request('status'))<a href="{{ route('lab-equipment.index', $trashed ? ['trashed' => 1] : []) }}" class="rounded-xl px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-100">Clear</a>@endif
                    <div class="sm:ml-auto">
                        @can('viewAny', \App\Models\LabEquipment::class)
                            @if ($trashed)
                                <a href="{{ route('lab-equipment.index') }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50">← Back to active</a>
                            @else
                                <a href="{{ route('lab-equipment.index', ['trashed' => 1]) }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100">View deleted</a>
                            @endif
                        @endcan
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Equipment</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($equipment as $item)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-900">{{ $item->name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $item->equipment_code }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $item->category ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $item->location ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-700' => $item->status === 'active',
                                        'bg-amber-100 text-amber-700' => $item->status === 'under_maintenance',
                                        'bg-slate-100 text-slate-600' => $item->status === 'decommissioned',
                                    ])>{{ ucfirst(str_replace('_', ' ', $item->status)) }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if ($trashed)
                                        @can('restore', $item)
                                            <form method="POST" action="{{ route('lab-equipment.restore', $item) }}" class="inline">@csrf<button class="text-sm font-semibold text-emerald-600 hover:text-emerald-800">Restore</button></form>
                                        @endcan
                                    @else
                                        @can('update', $item)<a href="{{ route('lab-equipment.edit', $item) }}" class="mr-3 text-sm font-semibold text-blue-600 hover:text-blue-800">Edit</a>@endcan
                                        @can('delete', $item)<form method="POST" action="{{ route('lab-equipment.destroy', $item) }}" class="inline" onsubmit="return confirm('Delete this equipment?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 hover:text-rose-800">Delete</button></form>@endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">No equipment registered.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $equipment->links() }}</div>
        </div>
    </div>
</x-app-layout>
