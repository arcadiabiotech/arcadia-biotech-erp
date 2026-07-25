@props(['history'])

<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Changed by</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Old rating</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">New rating</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Reason</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">IP address</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date &amp; time</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($history as $entry)
                <tr>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $entry->changedBy?->name ?? 'System' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $entry->old_score ?? '—' }}@if($entry->old_star !== null) ({{ $entry->old_star }}★) @endif</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $entry->new_score ?? '—' }}@if($entry->new_star !== null) ({{ $entry->new_star }}★) @endif</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $entry->reason ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-slate-400">{{ $entry->ip_address ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-slate-400">{{ $entry->created_at->format('d M Y, h:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No manual rating changes recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
