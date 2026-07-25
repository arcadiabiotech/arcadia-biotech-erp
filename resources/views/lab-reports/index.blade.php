<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <p class="text-sm font-semibold text-blue-600">LABORATORY OPERATIONS</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">Lab Reports</h1>
            <p class="mt-2 text-sm text-slate-500">Daily, weekly and monthly compliance and operational summaries.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $period => $label)
                <a href="{{ route('lab-reports.show', $period) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-blue-300 hover:shadow-md">
                    <p class="text-lg font-semibold text-slate-900">{{ $label }}</p>
                    <p class="mt-1 text-sm text-slate-500">Checklist compliance % and operational tiles for the {{ strtolower($label) }} period.</p>
                    <span class="mt-3 inline-block text-sm font-semibold text-blue-600">View report →</span>
                </a>
            @endforeach
        </div>
    </div>
</x-app-layout>
