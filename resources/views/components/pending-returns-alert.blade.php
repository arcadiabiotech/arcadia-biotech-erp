@props(['returns'])

@if($returns->isNotEmpty())
    <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 shadow-sm">
        <div class="mb-3 flex items-center gap-2">
            <svg class="h-5 w-5 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <h2 class="text-sm font-bold text-amber-800">Pending Crate Return <span class="font-normal text-amber-700">({{ $returns->count() }})</span></h2>
        </div>
        <div class="divide-y divide-amber-200/70">
            @foreach($returns as $line)
                <div class="flex flex-wrap items-center justify-between gap-3 py-2.5 text-sm">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-800">{{ $line->dispatch?->dispatch_no }} <span class="font-normal text-slate-400">· {{ $line->dispatch?->vehicle?->vehicle_no ?? 'No vehicle' }}</span></p>
                        <p class="truncate text-xs text-slate-500">Dealer: {{ $line->dealer?->dealer_name }} · Farmer: {{ $line->farmer?->farmer_name }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-4">
                        <div class="text-right text-xs text-slate-500">
                            <p>Total <span class="font-semibold text-slate-700">{{ rtrim(rtrim(number_format($line->crate_count, 2), '0'), '.') }}</span></p>
                            <p>Returned <span class="font-semibold text-emerald-700">{{ rtrim(rtrim(number_format((float) ($line->crates_returned ?? 0), 2), '0'), '.') }}</span></p>
                            <p>Pending <span class="font-semibold text-amber-700">{{ rtrim(rtrim(number_format($line->pending_crates, 2), '0'), '.') }}</span></p>
                        </div>
                        <a href="{{ route('dispatches.show', $line->dispatch) }}" class="shrink-0 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">View Dispatch</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
