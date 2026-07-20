<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">NETWORK</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Farmers</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $farmers->total() }} farmer{{ $farmers->total() === 1 ? '' : 's' }} on record.</p>
            </div>
            @can('create', \App\Models\Farmer::class)
                <a href="{{ route('farmers.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 4v16m8-8H4" /></svg>Add farmer</a>
            @endcan
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <input name="search" value="{{ request('search') }}" placeholder="Search name, code, mobile, Aadhaar..." class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:max-w-xs">

                    <select name="dealer" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All dealers</option>
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected(request('dealer') == $dealer->id)>{{ $dealer->dealer_name }}</option>
                        @endforeach
                    </select>

                    <select name="state" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All states</option>
                        @foreach($states as $state)
                            <option value="{{ $state->id }}" @selected(request('state') == $state->id)>{{ $state->name }}</option>
                        @endforeach
                    </select>

                    <select name="soil_type" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All soil types</option>
                        @foreach($soilTypes as $soil)
                            <option value="{{ $soil }}" @selected(request('soil_type') === $soil)>{{ $soil }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Any status</option>
                        <option value="1" @selected(request('status') === '1')>Active</option>
                        <option value="0" @selected(request('status') === '0')>Inactive</option>
                    </select>

                    @if ($trashed)<input type="hidden" name="trashed" value="1">@endif

                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    @if(request('search') || request('dealer') || request('state') || request('soil_type') || request('status') !== null)<a href="{{ route('farmers.index', $trashed ? ['trashed' => 1] : []) }}" class="rounded-xl px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-100">Clear</a>@endif

                    <div class="sm:ml-auto">
                        @can('viewAny', \App\Models\Farmer::class)
                            @if ($trashed)
                                <a href="{{ route('farmers.index') }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50">← Back to active farmers</a>
                            @else
                                <a href="{{ route('farmers.index', ['trashed' => 1]) }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100">View deleted farmers</a>
                            @endif
                        @endcan
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Farmer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dealer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($farmers as $farmer)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-900">{{ $farmer->farmer_name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $farmer->farmer_code }} · {{ $farmer->mobile }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $farmer->dealer?->dealer_name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $farmer->district?->name ?? '—' }}@if($farmer->state) , {{ $farmer->state->name }}@endif</td>
                                <td class="px-6 py-4"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-100 text-emerald-700' => $farmer->status, 'bg-slate-100 text-slate-600' => ! $farmer->status])>{{ $farmer->status ? 'Active' : 'Inactive' }}</span></td>
                                <td class="px-6 py-4 text-right">
                                    @if ($trashed)
                                        @can('restore', $farmer)
                                            <form method="POST" action="{{ route('farmers.restore', $farmer) }}" class="inline">@csrf<button class="text-sm font-semibold text-emerald-600 hover:text-emerald-800">Restore</button></form>
                                        @endcan
                                    @else
                                        @can('view', $farmer)<a href="{{ route('farmers.show', $farmer) }}" class="mr-3 text-sm font-semibold text-slate-600 hover:text-slate-800">Profile</a>@endcan
                                        @can('update', $farmer)<a href="{{ route('farmers.edit', $farmer) }}" class="mr-3 text-sm font-semibold text-blue-600 hover:text-blue-800">Edit</a>@endcan
                                        @can('delete', $farmer)<form method="POST" action="{{ route('farmers.destroy', $farmer) }}" class="inline" onsubmit="return confirm('Delete this farmer?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 hover:text-rose-800">Delete</button></form>@endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">No farmers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $farmers->links() }}</div>
        </div>
    </div>
</x-app-layout>
