<x-app-layout>
    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-600">ACCESS CONTROL</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Users</h1>
                <p class="mt-2 text-sm text-slate-500">Create logins and assign each user a role.</p>
            </div>
            @if (! $trashed)
                <a href="{{ route('users.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 4v16m8-8H4" /></svg>Add user</a>
            @endif
        </div>

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <input name="search" value="{{ request('search') }}" placeholder="Search users..." class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:max-w-xs">

                    <select name="role" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->display_name }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Any status</option>
                        <option value="1" @selected(request('status') === '1')>Active</option>
                        <option value="0" @selected(request('status') === '0')>Inactive</option>
                    </select>

                    @if ($trashed)<input type="hidden" name="trashed" value="1">@endif

                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    @if(request('search') || request('role') || request('status') !== null)<a href="{{ route('users.index', $trashed ? ['trashed' => 1] : []) }}" class="rounded-xl px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-100">Clear</a>@endif

                    <div class="sm:ml-auto">
                        @if ($trashed)
                            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50">← Back to active users</a>
                        @else
                            <a href="{{ route('users.index', ['trashed' => 1]) }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100">View deleted users</a>
                        @endif
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">User</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dealer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Last login</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($users as $user)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        @if($user->profilePhotoUrl())
                                            <img src="{{ $user->profilePhotoUrl() }}" alt="" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-blue-100 text-sm font-bold text-blue-700">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                        @endif
                                        <div>
                                            <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $user->email }} · {{ '@'.$user->username }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $user->role?->display_name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $user->dealer?->dealer_name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="px-6 py-4"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-100 text-emerald-700' => $user->status, 'bg-slate-100 text-slate-600' => ! $user->status])>{{ $user->status ? 'Active' : 'Inactive' }}</span></td>
                                <td class="px-6 py-4 text-right">
                                    @if ($trashed)
                                        <form method="POST" action="{{ route('users.restore', $user) }}" class="inline">@csrf<button class="text-sm font-semibold text-emerald-600 hover:text-emerald-800">Restore</button></form>
                                    @else
                                        @can('update', $user)<a href="{{ route('users.edit', $user) }}" class="mr-3 text-sm font-semibold text-blue-600 hover:text-blue-800">Edit</a>@endcan
                                        @can('delete', $user)<form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete this user?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 hover:text-rose-800">Delete</button></form>@endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">No users found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
