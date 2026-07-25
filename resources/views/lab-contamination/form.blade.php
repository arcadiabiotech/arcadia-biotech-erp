<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <a href="{{ route('lab-contamination.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to contamination records</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $labContamination->exists ? 'Edit contamination record' : 'Report contamination' }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $labContamination->exists ? $labContamination->contamination_no : 'A record number is generated automatically on save.' }}</p>
        </div>

        <form method="POST" action="{{ $labContamination->exists ? route('lab-contamination.update', $labContamination) : route('lab-contamination.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($labContamination->exists) @method('PUT') @endif

            <div class="grid gap-6 sm:grid-cols-2">
                @if($technicians->count() > 1)
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Reported by</label>
                        <select name="reported_by" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($labContamination->exists)>
                            @foreach($technicians as $technician)
                                <option value="{{ $technician->id }}" @selected(old('reported_by', $labContamination->reported_by ?? auth()->id()) == $technician->id)>{{ $technician->name }}</option>
                            @endforeach
                        </select>
                        @error('reported_by')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div>
                    <label class="text-sm font-semibold text-slate-700">Date</label>
                    <input type="date" name="contamination_date" value="{{ old('contamination_date', optional($labContamination->contamination_date)->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('contamination_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="text-sm font-semibold text-slate-700">Culture batch number</label><input name="culture_batch_number" value="{{ old('culture_batch_number', $labContamination->culture_batch_number) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('culture_batch_number')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Contamination type</label>
                    <select name="contamination_type" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach(\App\Models\LabContaminationRecord::TYPES as $type)
                            <option value="{{ $type }}" @selected(old('contamination_type', $labContamination->contamination_type) === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                    @error('contamination_type')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Severity</label>
                    <select name="severity" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach(\App\Models\LabContaminationRecord::SEVERITIES as $severity)
                            <option value="{{ $severity }}" @selected(old('severity', $labContamination->severity) === $severity)>{{ ucfirst($severity) }}</option>
                        @endforeach
                    </select>
                    @error('severity')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="text-sm font-semibold text-slate-700">Affected quantity</label><input type="number" min="0" name="affected_qty" value="{{ old('affected_qty', $labContamination->affected_qty) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('affected_qty')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Root cause</label><textarea name="root_cause" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('root_cause', $labContamination->root_cause) }}</textarea>@error('root_cause')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Corrective action</label><textarea name="corrective_action" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('corrective_action', $labContamination->corrective_action) }}</textarea>@error('corrective_action')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Remarks</label><textarea name="remarks" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks', $labContamination->remarks) }}</textarea>@error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Photo evidence</label>
                    <input type="file" name="photo" accept="image/*" capture="environment" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                    @if($labContamination->photo)<p class="mt-1 text-xs text-slate-500">Current: <a href="{{ \Illuminate\Support\Facades\Storage::url($labContamination->photo) }}" target="_blank" class="text-blue-600 hover:underline">view photo</a></p>@endif
                    @error('photo')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('lab-contamination.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $labContamination->exists ? 'Save changes' : 'Save record' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
