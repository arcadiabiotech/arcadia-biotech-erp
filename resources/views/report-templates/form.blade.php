<x-app-layout>
    <div class="mx-auto max-w-3xl">
        <div class="mb-8">
            <a href="{{ route('report-templates.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to report templates</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $reportTemplate->exists ? 'Edit report template' : 'Create report template' }}</h1>
        </div>

        <form method="POST" action="{{ $reportTemplate->exists ? route('report-templates.update', $reportTemplate) : route('report-templates.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($reportTemplate->exists) @method('PUT') @endif

            <div class="grid gap-6">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Name</label>
                    <input name="name" value="{{ old('name', $reportTemplate->name) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Date range type</label>
                    <select name="date_range_type" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach(\App\Models\ReportTemplate::DATE_RANGE_TYPES as $type)
                            <option value="{{ $type }}" @selected(old('date_range_type', $reportTemplate->date_range_type) === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                    @error('date_range_type')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Modules to include</label>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach(\App\Models\ReportTemplate::MODULES as $key => $label)
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, old('modules', $reportTemplate->modules ?? []), true)) class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('modules')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('report-templates.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $reportTemplate->exists ? 'Save changes' : 'Create report' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
