<x-app-layout>
    <div class="mx-auto max-w-3xl">
        <div class="mb-8">
            <a href="{{ route('dealer-assignments.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to assignments</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $assignment->exists ? 'Edit assignment' : 'Assign dealer' }}</h1>
            <p class="mt-2 text-sm text-slate-500">Link a dealer to a marketing user. A dealer can belong to only one marketing user.</p>
        </div>

        <form method="POST" action="{{ $assignment->exists ? route('dealer-assignments.update', $assignment) : route('dealer-assignments.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($assignment->exists) @method('PUT') @endif

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Dealer</label>
                    <select name="dealer_id" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select dealer —</option>
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected(old('dealer_id', $assignment->dealer_id) == $dealer->id)>{{ $dealer->dealer_name }} @if($dealer->firm_name)({{ $dealer->firm_name }})@endif @if($dealer->dealer_code) · {{ $dealer->dealer_code }}@endif</option>
                        @endforeach
                    </select>
                    @error('dealer_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Marketing user</label>
                    <select name="marketing_user_id" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select user —</option>
                        @foreach($marketingUsers as $user)
                            <option value="{{ $user->id }}" @selected(old('marketing_user_id', $assignment->marketing_user_id) == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('marketing_user_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Assigned date</label>
                    <input type="date" name="assigned_date" value="{{ old('assigned_date', optional($assignment->assigned_date)->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('assigned_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Status</label>
                    <select name="status" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="1" @selected(old('status', $assignment->status) == 1)>Active</option>
                        <option value="0" @selected(old('status', $assignment->status) == 0)>Inactive</option>
                    </select>
                    @error('status')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('dealer-assignments.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $assignment->exists ? 'Save changes' : 'Assign dealer' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
