<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <a href="{{ route('lab-checklists.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to checklists</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $labChecklist->exists ? 'Edit checklist' : 'Daily Checklist' }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $labChecklist->exists ? $labChecklist->checklist_no : 'Morning checks and daily report — one checklist per employee per day.' }}</p>
        </div>

        <form method="POST" action="{{ $labChecklist->exists ? route('lab-checklists.update', $labChecklist) : route('lab-checklists.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($labChecklist->exists) @method('PUT') @endif

            <div class="grid gap-6 sm:grid-cols-2">
                @if($employees->count() > 1)
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Employee</label>
                        <select name="employee_id" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($labChecklist->exists)>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('employee_id', $labChecklist->employee_id ?? auth()->id()) == $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        @error('employee_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div>
                    <label class="text-sm font-semibold text-slate-700">Date</label>
                    <input type="date" name="checklist_date" value="{{ old('checklist_date', optional($labChecklist->checklist_date)->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('checklist_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            @php
                $existingItems = $labChecklist->relationLoaded('checklistItems') ? $labChecklist->checklistItems->keyBy('item_key') : collect();
                $morningItems = collect(\App\Models\LabDailyChecklist::CHECKLIST_ITEMS)->where('section', 'morning');
                $dailyReportItems = collect(\App\Models\LabDailyChecklist::CHECKLIST_ITEMS)->where('section', 'daily_report');
            @endphp

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Morning checklist</div>
            <div class="space-y-3">
                @foreach($morningItems as $key => $meta)
                    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <label class="flex items-center gap-3 text-sm font-semibold text-slate-700">
                            <input type="checkbox" name="items[{{ $key }}][done]" value="1" @checked(old("items.$key.done", $existingItems[$key]->is_done ?? false)) class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            {{ $meta['label'] }}
                        </label>
                        <input type="time" name="items[{{ $key }}][time]" value="{{ old("items.$key.time", $existingItems[$key]->time_recorded ?? '') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    @error("items.$key.time")<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                @endforeach
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Daily report</div>
            <div class="space-y-3">
                @foreach($dailyReportItems as $key => $meta)
                    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <label class="flex items-center gap-3 text-sm font-semibold text-slate-700">
                            <input type="checkbox" name="items[{{ $key }}][done]" value="1" @checked(old("items.$key.done", $existingItems[$key]->is_done ?? false)) class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            {{ $meta['label'] }}
                        </label>
                        <div class="flex items-center gap-2">
                            @if($meta['chemical'])
                                <input name="items[{{ $key }}][chemical]" value="{{ old('items.'.$key.'.chemical', $existingItems[$key]->chemical_used ?? '') }}" placeholder="Chemical used" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:w-40">
                            @endif
                            <input type="time" name="items[{{ $key }}][time]" value="{{ old("items.$key.time", $existingItems[$key]->time_recorded ?? '') }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    @error("items.$key.time")<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                    @if($meta['chemical'])@error("items.$key.chemical")<p class="text-sm text-rose-600">{{ $message }}</p>@enderror @endif
                @endforeach
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Notes & photo</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">General remarks</label><textarea name="general_remarks" rows="3" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('general_remarks', $labChecklist->general_remarks) }}</textarea>@error('general_remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Photo evidence</label>
                    <input type="file" name="photo" accept="image/*" capture="environment" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                    @if($labChecklist->photo)<p class="mt-1 text-xs text-slate-500">Current: <a href="{{ \Illuminate\Support\Facades\Storage::url($labChecklist->photo) }}" target="_blank" class="text-blue-600 hover:underline">view photo</a></p>@endif
                    @error('photo')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('lab-checklists.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $labChecklist->exists ? 'Save changes' : 'Save checklist' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
