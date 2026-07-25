<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <a href="{{ route('lab-media.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to media verifications</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $labMedia->exists ? 'Edit verification' : 'New media stock verification' }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $labMedia->exists ? $labMedia->verification_no : 'A verification number is generated automatically on save.' }}</p>
        </div>

        <form method="POST" action="{{ $labMedia->exists ? route('lab-media.update', $labMedia) : route('lab-media.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($labMedia->exists) @method('PUT') @endif

            <div class="grid gap-6 sm:grid-cols-2">
                @if($technicians->count() > 1)
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Verified by</label>
                        <select name="verified_by" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($labMedia->exists)>
                            @foreach($technicians as $technician)
                                <option value="{{ $technician->id }}" @selected(old('verified_by', $labMedia->verified_by ?? auth()->id()) == $technician->id)>{{ $technician->name }}</option>
                            @endforeach
                        </select>
                        @error('verified_by')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div><label class="text-sm font-semibold text-slate-700">Media name</label><input name="media_name" value="{{ old('media_name', $labMedia->media_name) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('media_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Verification date</label>
                    <input type="date" name="verification_date" value="{{ old('verification_date', optional($labMedia->verification_date)->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('verification_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="text-sm font-semibold text-slate-700">Unit</label><input name="unit" value="{{ old('unit', $labMedia->unit) }}" placeholder="litres, packets, kg..." required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('unit')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Reorder level</label><input type="number" step="0.01" min="0" name="reorder_level" value="{{ old('reorder_level', $labMedia->reorder_level) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('reorder_level')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Opening stock</label><input type="number" step="0.01" min="0" name="opening_stock" value="{{ old('opening_stock', $labMedia->opening_stock) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('opening_stock')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Received qty</label><input type="number" step="0.01" min="0" name="received_qty" value="{{ old('received_qty', $labMedia->received_qty) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('received_qty')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Consumed qty</label><input type="number" step="0.01" min="0" name="consumed_qty" value="{{ old('consumed_qty', $labMedia->consumed_qty) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('consumed_qty')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Remarks</label><textarea name="remarks" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks', $labMedia->remarks) }}</textarea>@error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Photo evidence</label>
                    <input type="file" name="photo" accept="image/*" capture="environment" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                    @if($labMedia->photo)<p class="mt-1 text-xs text-slate-500">Current: <a href="{{ \Illuminate\Support\Facades\Storage::url($labMedia->photo) }}" target="_blank" class="text-blue-600 hover:underline">view photo</a></p>@endif
                    @error('photo')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('lab-media.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $labMedia->exists ? 'Save changes' : 'Save verification' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
