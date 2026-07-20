<x-app-layout>
    <div class="mx-auto max-w-3xl">
        <div class="mb-8">
            <a href="{{ route('users.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to users</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $user->exists ? 'Edit user' : 'Create user' }}</h1>
            <p class="mt-2 text-sm text-slate-500">Set login details and assign a role. Dealer/Marketing assignment depends on the role chosen.</p>
        </div>

        @if ($user->exists)
            <div class="mb-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Last login</p><p class="mt-1 text-sm font-medium text-slate-700">{{ $user->last_login_at?->format('d M Y, h:i A') ?? 'Never logged in' }}</p><p class="text-xs text-slate-500">{{ $user->last_login_ip ?? '—' }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Recent activity</p>@forelse($activity->take(4) as $entry)<p class="mt-1 text-xs text-slate-600">{{ ucfirst($entry->action) }} · {{ $entry->created_at->diffForHumans() }}</p>@empty<p class="mt-1 text-sm text-slate-400">No activity recorded yet.</p>@endforelse</div>
            </div>
        @endif

        <form
            x-data="{
                roles: {{ Js::from($roles->map(fn ($r) => ['id' => (string) $r->id, 'name' => $r->name])) }},
                roleId: '{{ old('role_id', $user->role_id) }}',
                get roleName() { return (this.roles.find(r => r.id === this.roleId) || {}).name ?? '' },
            }"
            method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}"
            enctype="multipart/form-data"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($user->exists) @method('PUT') @endif

            <div class="mb-8 flex items-center gap-5">
                @if($user->profilePhotoUrl())
                    <img src="{{ $user->profilePhotoUrl() }}" alt="" class="h-16 w-16 rounded-full object-cover">
                @else
                    <span class="grid h-16 w-16 place-items-center rounded-full bg-blue-100 text-xl font-bold text-blue-700">{{ strtoupper(mb_substr($user->name ?: '?', 0, 1)) }}</span>
                @endif
                <div>
                    <label class="text-sm font-semibold text-slate-700">Profile photo <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="file" name="profile_photo" accept="image/*" class="mt-2 block text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                    @error('profile_photo')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div><label class="text-sm font-semibold text-slate-700">Full name</label><input name="name" value="{{ old('name', $user->name) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Username</label><input name="username" value="{{ old('username', $user->username) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('username')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('email')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Mobile</label><input name="mobile" value="{{ old('mobile', $user->mobile) }}" required maxlength="10" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('mobile')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Role</label>
                    <select name="role_id" x-model="roleId" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select role —</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                        @endforeach
                    </select>
                    @error('role_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Status</label>
                    <select name="status" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="1" @selected(old('status', $user->status) == 1)>Active</option>
                        <option value="0" @selected(old('status', $user->status) == 0)>Inactive</option>
                    </select>
                    @error('status')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div x-show="roleName === 'dealer'">
                    <label class="text-sm font-semibold text-slate-700">Dealer</label>
                    <select name="dealer_id" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select dealer —</option>
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected(old('dealer_id', $user->dealer_id) == $dealer->id)>{{ $dealer->dealer_name }}</option>
                        @endforeach
                    </select>
                    @error('dealer_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div x-show="roleName === 'marketing'" x-cloak class="sm:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Dealers managed</label>
                    <p class="mt-1 text-xs text-slate-500">A dealer can only be managed by one Marketing user at a time — dealers already assigned elsewhere won't be released by selecting them here.</p>
                    <select name="dealer_ids[]" multiple size="6" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected(in_array($dealer->id, old('dealer_ids', $assignedDealerIds)))>{{ $dealer->dealer_name }} @if($dealer->assignment && ! in_array($dealer->id, $assignedDealerIds)) (assigned to {{ $dealer->assignment->marketingUser?->name }}) @endif</option>
                        @endforeach
                    </select>
                    @error('dealer_ids')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div><label class="text-sm font-semibold text-slate-700">Password @if($user->exists)<span class="font-normal text-slate-400">(leave blank to keep current)</span>@endif</label><input type="password" name="password" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('password')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('users.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $user->exists ? 'Save changes' : 'Create user' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
