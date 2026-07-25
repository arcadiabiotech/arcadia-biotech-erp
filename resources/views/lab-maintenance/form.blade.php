<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <a href="{{ route('lab-maintenance.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to maintenance logs</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $labMaintenance->exists ? 'Edit maintenance log' : 'New maintenance log' }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $labMaintenance->exists ? $labMaintenance->log_no : 'A log number is generated automatically on save.' }}</p>
        </div>

        <form method="POST" action="{{ $labMaintenance->exists ? route('lab-maintenance.update', $labMaintenance) : route('lab-maintenance.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($labMaintenance->exists) @method('PUT') @endif

            <div class="grid gap-6 sm:grid-cols-2">
                @if($technicians->count() > 1)
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Performed by</label>
                        <select name="performed_by" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($labMaintenance->exists)>
                            @foreach($technicians as $technician)
                                <option value="{{ $technician->id }}" @selected(old('performed_by', $labMaintenance->performed_by ?? auth()->id()) == $technician->id)>{{ $technician->name }}</option>
                            @endforeach
                        </select>
                        @error('performed_by')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div>
                    <label class="text-sm font-semibold text-slate-700">Equipment</label>
                    <select name="lab_equipment_id" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select equipment —</option>
                        @foreach($equipmentOptions as $equipment)
                            <option value="{{ $equipment->id }}" @selected(old('lab_equipment_id', $labMaintenance->lab_equipment_id) == $equipment->id)>{{ $equipment->name }} ({{ $equipment->equipment_code }})</option>
                        @endforeach
                    </select>
                    @error('lab_equipment_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Maintenance date</label>
                    <input type="date" name="maintenance_date" value="{{ old('maintenance_date', optional($labMaintenance->maintenance_date)->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('maintenance_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Type</label>
                    <select name="maintenance_type" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach(\App\Models\LabEquipmentMaintenanceLog::MAINTENANCE_TYPES as $type)
                            <option value="{{ $type }}" @selected(old('maintenance_type', $labMaintenance->maintenance_type) === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                    @error('maintenance_type')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Next due date</label>
                    <input type="date" name="next_due_date" value="{{ old('next_due_date', optional($labMaintenance->next_due_date)->format('Y-m-d')) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('next_due_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Downtime (hours)</label>
                    <input type="number" step="0.01" min="0" name="downtime_hours" value="{{ old('downtime_hours', $labMaintenance->downtime_hours) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('downtime_hours')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Cost (₹)</label>
                    <input type="number" step="0.01" min="0" name="cost" value="{{ old('cost', $labMaintenance->cost) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('cost')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Description</label><textarea name="description" rows="3" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $labMaintenance->description) }}</textarea>@error('description')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Remarks</label><textarea name="remarks" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks', $labMaintenance->remarks) }}</textarea>@error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Photo evidence</label>
                    <input type="file" name="photo" accept="image/*" capture="environment" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                    @if($labMaintenance->photo)<p class="mt-1 text-xs text-slate-500">Current: <a href="{{ \Illuminate\Support\Facades\Storage::url($labMaintenance->photo) }}" target="_blank" class="text-blue-600 hover:underline">view photo</a></p>@endif
                    @error('photo')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('lab-maintenance.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $labMaintenance->exists ? 'Save changes' : 'Save log' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
