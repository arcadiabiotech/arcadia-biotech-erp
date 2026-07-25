<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lab Report — {{ ucfirst($period) }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1e293b; font-size: 13px; margin: 32px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e2e8f0; }
        th { color: #64748b; font-size: 11px; text-transform: uppercase; }
        .amount-table td:last-child, .amount-table th:last-child { text-align: right; }
        .header { display: table; width: 100%; margin-bottom: 16px; }
        .header .col { display: table-cell; vertical-align: top; width: 50%; }
        @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="col">
            <h1>Arcadia Biotech</h1>
            <p class="muted">Laboratory Daily Operations &amp; Maintenance — {{ ucfirst($period) }} Report</p>
        </div>
        <div class="col" style="text-align:right">
            <p><strong>{{ $report['label'] }}</strong></p>
            <p class="muted">Generated {{ now()->format('d M Y, h:i A') }}</p>
        </div>
    </div>

    <table>
        <tr><th style="width:40%">Checklist compliance</th><td>{{ $report['compliance'] }}% ({{ $report['checklistsApproved'] }} approved of {{ $report['checklistsSubmitted'] }} submitted)</td></tr>
        @foreach(\App\Models\LabDailyChecklist::CHECKLIST_ITEMS as $key => $meta)
            <tr><th>{{ $meta['label'] }} compliance</th><td>{{ $report['itemCompliance'][$key] }}%</td></tr>
        @endforeach
    </table>

    <table class="amount-table">
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Equipment maintenance logs</td><td>{{ $report['maintenanceCount'] }}</td></tr>
        <tr><td>Overdue maintenance</td><td>{{ $report['overdueMaintenance'] }}</td></tr>
        <tr><td>Media verifications</td><td>{{ $report['mediaCount'] }}</td></tr>
        <tr><td>Low stock media items</td><td>{{ $report['lowStockMedia'] }}</td></tr>
        <tr><td>Chemical usage entries</td><td>{{ $report['chemicalCount'] }}</td></tr>
        <tr><td>Contamination incidents</td><td>{{ $report['contaminationCount'] }}</td></tr>
        <tr><td>Critical contamination incidents</td><td>{{ $report['criticalContamination'] }}</td></tr>
    </table>
</body>
</html>
