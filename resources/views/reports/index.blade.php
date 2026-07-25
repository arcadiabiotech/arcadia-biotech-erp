<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="mb-8">
            <p class="text-sm font-semibold text-blue-600">ANALYTICS</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">Reports</h1>
            <p class="mt-2 text-sm text-slate-500">Export any register below as CSV, or open the Ledger for a live outstanding statement.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @forelse($reports as $report)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900">{{ $report['title'] }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $report['description'] }}</p>
                    <a href="{{ route($report['route']) }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16" /></svg>
                        @switch($report['route'])
                            @case('ledger.export')
                                Export
                                @break
                            @case('lab-reports.index')
                            @case('reports.crate-returns')
                                View Report
                                @break
                            @default
                                Export CSV
                        @endswitch
                    </a>
                    @if($report['route'] === 'ledger.export')
                        <a href="{{ route('ledger.outstanding') }}" class="mt-4 ml-2 inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">View statement</a>
                    @endif
                </div>
            @empty
                <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-500 shadow-sm">
                    No reports available for your role yet.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
