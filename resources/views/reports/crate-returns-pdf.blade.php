<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Crate Return Report</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            color: #1e293b;
            font-size: 10px;
            margin: 0;
        }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .subtitle { color: #64748b; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: 4px 6px; text-align: left; }
        th { background: #f1f5f9; text-transform: uppercase; font-size: 8px; letter-spacing: 0.03em; color: #64748b; }
        .num { text-align: right; }
        .summary { width: 100%; margin-bottom: 10px; }
        .summary td { border: none; padding: 2px 12px 2px 0; }
        .summary .label { color: #64748b; }
        .status-pending { color: #b45309; font-weight: bold; }
        .status-returned { color: #047857; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Crate Return Report</h1>
    <p class="subtitle">Generated {{ now()->format('d M Y, h:i A') }}</p>

    <table class="summary">
        <tr>
            <td class="label">Total crates</td><td><strong>{{ $summary['total'] }}</strong></td>
            <td class="label">Returned</td><td><strong>{{ $summary['returned'] }}</strong></td>
            <td class="label">Damaged</td><td><strong>{{ $summary['damaged'] }}</strong></td>
            <td class="label">Pending</td><td><strong>{{ $summary['pending'] }}</strong></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Dispatch</th>
                <th>Vehicle</th>
                <th>Dealer</th>
                <th>Farmer</th>
                <th>Return date</th>
                <th class="num">Total</th>
                <th class="num">Returned</th>
                <th class="num">Damaged</th>
                <th class="num">Pending</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td>{{ $line->dispatch?->dispatch_no }}</td>
                    <td>{{ $line->dispatch?->vehicle?->vehicle_no ?? '-' }}</td>
                    <td>{{ $line->dealer?->dealer_name }}</td>
                    <td>{{ $line->farmer?->farmer_name }}</td>
                    <td>{{ $line->dispatch?->vehicle_returned_at?->format('d M Y') ?? '-' }}</td>
                    <td class="num">{{ $line->crate_count }}</td>
                    <td class="num">{{ (float) ($line->crates_returned ?? 0) }}</td>
                    <td class="num">{{ (float) ($line->damage_qty ?? 0) }}</td>
                    <td class="num">{{ $line->pending_crates }}</td>
                    <td class="{{ $line->pending_crates > 0 ? 'status-pending' : 'status-returned' }}">{{ $line->pending_crates > 0 ? 'Pending' : 'Returned' }}</td>
                </tr>
            @empty
                <tr><td colspan="10">No crate-tracked dispatches match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
