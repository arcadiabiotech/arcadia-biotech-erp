<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt {{ $payment->payment_no }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1e293b; font-size: 13px; margin: 32px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e2e8f0; }
        th { color: #64748b; font-size: 11px; text-transform: uppercase; width: 30%; }
        .header { display: table; width: 100%; margin-bottom: 16px; }
        .header .col { display: table-cell; vertical-align: top; width: 50%; }
        .amount { margin-top: 20px; font-size: 16px; font-weight: bold; text-align: right; }
        .signature { display: table; width: 100%; margin-top: 60px; }
        .signature .col { display: table-cell; width: 50%; padding-top: 30px; border-top: 1px solid #94a3b8; text-align: center; font-size: 11px; color: #64748b; }
        @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="col">
            <h1>Arcadia Biotech</h1>
            <p class="muted">Banana Tissue Culture · Payment Receipt</p>
        </div>
        <div class="col" style="text-align:right">
            <p><strong>{{ $payment->payment_no }}</strong></p>
            <p class="muted">{{ $payment->payment_date?->format('d M Y') }}</p>
        </div>
    </div>

    <table>
        <tr><th>Invoice</th><td>{{ $payment->invoice?->invoice_no }}</td></tr>
        <tr><th>Dealer</th><td>{{ $payment->dealer?->dealer_name }} ({{ $payment->dealer?->dealer_code }})</td></tr>
        <tr><th>Farmer</th><td>{{ $payment->farmer?->farmer_name }} ({{ $payment->farmer?->farmer_code }})</td></tr>
        <tr><th>Payment mode</th><td>{{ $payment->payment_mode }}</td></tr>
        <tr><th>Reference no.</th><td>{{ $payment->reference_no ?? '—' }}</td></tr>
        <tr><th>Bank</th><td>{{ $payment->bank_name ?? '—' }}</td></tr>
    </table>

    <p class="amount">Amount received: ₹{{ number_format((float) $payment->amount, 2) }}</p>

    @if($payment->remarks)
        <p class="muted" style="margin-top:16px"><strong>Remarks:</strong> {{ $payment->remarks }}</p>
    @endif

    <div class="signature">
        <div class="col">Received by</div>
        <div class="col">Authorized signatory</div>
    </div>
</body>
</html>
