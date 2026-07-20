<x-app-layout>
    @php($canManage = auth()->user()->hasRole(['super-admin', 'admin']))
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">MARKETING</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Dealer Assignments</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $canManage ? 'Assign dealers to marketing users and track ownership.' : 'Dealers assigned to you.' }}</p>
            </div>
            @if ($canManage)
                <a href="{{ route('dealer-assignments.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 4v16m8-8H4" /></svg>Assign dealer
                </a>
            @endif
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <form class="flex flex-col gap-3 sm:flex-row">
                    <input name="search" value="{{ request('search') }}" placeholder="Search dealers..." class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:max-w-sm">
                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Search</button>
                    @if(request('search'))<a href="{{ route('dealer-assignments.index') }}" class="rounded-xl px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-100">Clear</a>@endif
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dealer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Marketing user</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Assigned by</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Assigned date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            @if ($canManage)<th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($assignments as $assignment)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-900">{{ $assignment->dealer?->dealer_name ?? '—' }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $assignment->dealer?->firm_name }} @if($assignment->dealer?->dealer_code) · {{ $assignment->dealer->dealer_code }} @endif</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $assignment->marketingUser?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $assignment->assignedBy?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $assignment->assigned_date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-100 text-emerald-700' => $assignment->status, 'bg-slate-100 text-slate-600' => ! $assignment->status])>{{ $assignment->status ? 'Active' : 'Inactive' }}</span>
                                </td>
                                @if ($canManage)
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('dealer-assignments.edit', $assignment) }}" class="mr-3 text-sm font-semibold text-blue-600 hover:text-blue-800">Edit</a>
                                        <form method="POST" action="{{ route('dealer-assignments.destroy', $assignment) }}" class="inline" onsubmit="return confirm('Remove this assignment?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 hover:text-rose-800">Remove</button></form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="px-6 py-12 text-center text-sm text-slate-500">No dealer assignments found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $assignments->links() }}</div>
        </div>
    </div>
</x-app-layout>
