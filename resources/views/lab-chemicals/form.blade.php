<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <a href="{{ route('lab-chemicals.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to chemical usage</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $labChemical->exists ? 'Edit chemical usage' : 'Log chemical usage' }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $labChemical->exists ? $labChemical->usage_no : 'A usage number is generated automatically on save.' }}</p>
        </div>

        <form method="POST" action="{{ $labChemical->exists ? route('lab-chemicals.update', $labChemical) : route('lab-chemicals.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($labChemical->exists) @method('PUT') @endif

            <div class="grid gap-6 sm:grid-cols-2">
                @if($technicians->count() > 1)
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Used by</label>
                        <select name="used_by" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($labChemical->exists)>
                            @foreach($technicians as $technician)
                                <option value="{{ $technician->id }}" @selected(old('used_by', $labChemical->used_by ?? auth()->id()) == $technician->id)>{{ $technician->name }}</option>
                            @endforeach
                        </select>
                        @error('used_by')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div><label class="text-sm font-semibold text-slate-700">Chemical name</label><input name="chemical_name" value="{{ old('chemical_name', $labChemical->chemical_name) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('chemical_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Usage date</label>
                    <input type="date" name="usage_date" value="{{ old('usage_date', optional($labChemical->usage_date)->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('usage_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="text-sm font-semibold text-slate-700">Quantity used</label><input type="number" step="0.01" min="0.01" name="quantity_used" value="{{ old('quantity_used', $labChemical->quantity_used) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('quantity_used')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Unit</label><input name="unit" value="{{ old('unit', $labChemical->unit) }}" placeholder="ml, litres, grams..." required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('unit')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Batch number</label><input name="batch_number" value="{{ old('batch_number', $labChemical->batch_number) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('batch_number')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Purpose</label><input name="purpose" value="{{ old('purpose', $labChemical->purpose) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('purpose')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Remarks</label><textarea name="remarks" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks', $labChemical->remarks) }}</textarea>@error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Photo evidence</label>
                    <input type="file" name="photo" accept="image/*" capture="environment" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                    @if($labChemical->photo)<p class="mt-1 text-xs text-slate-500">Current: <a href="{{ \Illuminate\Support\Facades\Storage::url($labChemical->photo) }}" target="_blank" class="text-blue-600 hover:underline">view photo</a></p>@endif
                    @error('photo')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('lab-chemicals.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $labChemical->exists ? 'Save changes' : 'Save entry' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
