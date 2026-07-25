<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <a href="{{ route('lab-equipment.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to equipment</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $labEquipment->exists ? 'Edit equipment' : 'Add equipment' }}</h1>
        </div>

        <form method="POST" action="{{ $labEquipment->exists ? route('lab-equipment.update', $labEquipment) : route('lab-equipment.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($labEquipment->exists) @method('PUT') @endif

            <div class="grid gap-6 sm:grid-cols-2">
                <div><label class="text-sm font-semibold text-slate-700">Equipment code</label><input name="equipment_code" value="{{ old('equipment_code', $labEquipment->equipment_code) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('equipment_code')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Name</label><input name="name" value="{{ old('name', $labEquipment->name) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Category</label><input name="category" value="{{ old('category', $labEquipment->category) }}" placeholder="Autoclave, Laminar Flow, UV Chamber..." class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('category')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Location</label><input name="location" value="{{ old('location', $labEquipment->location) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('location')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Status</label>
                    <select name="status" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach(\App\Models\LabEquipment::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', $labEquipment->status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Remarks</label><textarea name="remarks" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks', $labEquipment->remarks) }}</textarea>@error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('lab-equipment.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $labEquipment->exists ? 'Save changes' : 'Add equipment' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
