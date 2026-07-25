<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="mb-8">
            <a href="{{ route('report-templates.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to report templates</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $reportTemplate->name }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ ucfirst($reportTemplate->date_range_type) }} · {{ $label }}</p>
        </div>

        <form class="mb-6 flex items-center gap-3">
            <label class="text-sm font-semibold text-slate-700">Jump to date</label>
            <input type="date" name="date" value="{{ request('date', now()->toDateString()) }}" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Go</button>
        </form>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($metrics as $module => $data)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">{{ $data['label'] }}</h2>
                    <dl class="grid gap-3 text-sm">
                        @foreach($data as $key => $value)
                            @continue($key === 'label')
                            <div class="flex justify-between"><dt class="text-slate-500">{{ $key }}</dt><dd class="font-medium text-slate-800">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
