@props(['type', 'model', 'canEdit' => false])

@php
    $rating = $model->ratingRecord;
    $isManual = (bool) ($rating?->is_manual_override);
    $autoScore = $rating?->auto_score;
    $autoStar = $rating?->auto_star;
    $currentScore = $isManual ? $rating?->manual_score : $autoScore;
    $currentStar = $isManual ? $rating?->manual_star : $autoStar;
@endphp

<div @if($canEdit) x-data="{
    open: false,
    mode: '{{ $isManual ? 'manual' : 'auto' }}',
    score: {{ $rating?->manual_score ?? $autoScore ?? 0 }},
    reason: @js($rating?->manual_reason ?? ''),
    stars() { return this.score > 0 ? Math.min(5, Math.max(1, Math.ceil(this.score / 2))) : 0 },
}" @endif class="inline-flex items-center gap-2">
    <span @class([
        'inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-semibold',
        'bg-amber-100 text-amber-700' => $isManual,
        'bg-blue-100 text-blue-700' => ! $isManual,
    ])>
        {{ $isManual ? '✏️ Manual Rating' : '⭐ Auto Rating' }}
        @if($currentScore !== null) · {{ $currentScore }}/10 ({{ str_repeat('★', (int) $currentStar) }}{{ str_repeat('☆', 5 - (int) $currentStar) }}) @endif
    </span>

    @if($canEdit)
        <button type="button" @click="open = true" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">⭐ Edit Rating</button>

        <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6">
            <div class="fixed inset-0 bg-slate-950/60" @click="open = false"></div>

            <div class="relative mx-auto w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">Edit Rating</h2>
                    <button type="button" @click="open = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </div>

                <div class="mb-5 rounded-xl bg-slate-50 p-4 text-sm">
                    <p class="font-semibold text-slate-500">Previous auto rating</p>
                    <p class="mt-1 text-slate-800">{{ $autoScore ?? '—' }}{{ $autoScore !== null ? ' / 10' : '' }} @if($autoStar !== null) · {{ str_repeat('★', (int) $autoStar) }}{{ str_repeat('☆', 5 - (int) $autoStar) }} @endif</p>
                </div>

                <div class="mb-5 flex gap-1 rounded-xl border border-slate-200 bg-white p-1">
                    <button type="button" @click="mode = 'auto'" :class="mode === 'auto' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">Auto Rating</button>
                    <button type="button" @click="mode = 'manual'" :class="mode === 'manual' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">Manual Rating</button>
                </div>

                <div x-show="mode === 'manual'" x-cloak class="space-y-4">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Manual Score (0-10)</label>
                        <input type="number" min="0" max="10" x-model.number="score" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-1 text-sm text-slate-500">Stars: <span x-text="'★'.repeat(stars()) + '☆'.repeat(5 - stars())"></span></p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Reason for Manual Change <span class="font-normal text-slate-400">(required)</span></label>
                        <textarea x-model="reason" rows="3" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    <form method="POST" action="{{ route('ratings.update', [$type, $model->id]) }}" class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="score" :value="score">
                        <input type="hidden" name="reason" :value="reason">
                        <button type="button" @click="open = false" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                        <button type="submit" :disabled="! reason || score === null || score === ''" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50">Save</button>
                    </form>
                </div>

                <div x-show="mode === 'auto'" x-cloak>
                    <p class="text-sm text-slate-500">Switching to Auto Rating clears the manual override — this record will use the automatically calculated score again, including in the next nightly recalculation.</p>
                    <form method="POST" action="{{ route('ratings.reset', [$type, $model->id]) }}" class="mt-5 flex justify-end gap-3 border-t border-slate-100 pt-4">
                        @csrf
                        <button type="button" @click="open = false" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                        <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Save</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
