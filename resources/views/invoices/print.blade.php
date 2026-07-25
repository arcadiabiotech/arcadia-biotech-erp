<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_no }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1e293b; font-size: 13px; margin: 32px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e2e8f0; }
        th { color: #64748b; font-size: 11px; text-transform: uppercase; width: 25%; }
        .header { display: table; width: 100%; margin-bottom: 16px; }
        .header .col { display: table-cell; vertical-align: top; width: 50%; }
        .totals { width: 50%; margin-left: auto; margin-top: 16px; }
        .totals td { border-bottom: none; padding: 4px 6px; }
        .totals .grand { font-weight: bold; border-top: 1px solid #94a3b8; }
        .signature { display: table; width: 100%; margin-top: 60px; }
        .signature .col { display: table-cell; width: 33%; padding-top: 30px; border-top: 1px solid #94a3b8; text-align: center; font-size: 11px; color: #64748b; }
        @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="col">
            <h1>Arcadia Biotech</h1>
            <p class="muted">Banana Tissue Culture · Tax Invoice</p>
        </div>
        <div class="col" style="text-align:right">
            <p><strong>{{ $invoice->invoice_no }}</strong></p>
            <p class="muted">{{ $invoice->invoice_date?->format('d M Y') }}</p>
            <p class="muted">Status: {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</p>
        </div>
    </div>

    <table>
        <tr><th>Dispatch</th><td>{{ $invoice->dispatch?->dispatch_no }}</td></tr>
        <tr><th>Dealer</th><td>{{ $invoice->dealer?->dealer_name }} ({{ $invoice->dealer?->dealer_code }})</td></tr>
    </table>

    <table>
        <thead>
            <tr><th style="width:auto">Booking</th><th style="width:auto">Farmer</th><th style="width:auto; text-align:right">Qty</th><th style="width:auto; text-align:right">Rate</th><th style="width:auto; text-align:right">Amount</th></tr>
        </thead>
        <tbody>
            @foreach($invoice->lines as $line)
                <tr>
                    <td>{{ $line->booking?->booking_no }}</td>
                    <td>{{ $line->farmer?->farmer_name }}</td>
                    <td style="text-align:right">{{ number_format($line->qty) }}</td>
                    <td style="text-align:right">₹{{ number_format((float) $line->rate, 2) }}</td>
                    <td style="text-align:right">₹{{ number_format((float) $line->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">Subtotal</td><td style="text-align:right">₹{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        <tr><td class="muted">Discount</td><td style="text-align:right">₹{{ number_format((float) $invoice->discount, 2) }}</td></tr>
        <tr><td class="muted">Tax</td><td style="text-align:right">₹{{ number_format((float) $invoice->tax, 2) }}</td></tr>
        <tr class="grand"><td>Grand total</td><td style="text-align:right">₹{{ number_format((float) $invoice->grand_total, 2) }}</td></tr>
        <tr><td class="muted">Paid</td><td style="text-align:right">₹{{ number_format((float) $invoice->paid_amount, 2) }}</td></tr>
        <tr><td class="muted">Balance due</td><td style="text-align:right">₹{{ number_format((float) $invoice->balance_amount, 2) }}</td></tr>
    </table>

    @if($invoice->remarks)
        <p class="muted" style="margin-top:16px"><strong>Remarks:</strong> {{ $invoice->remarks }}</p>
    @endif

    <div class="signature">
        <div class="col">Dealer signature</div>
        <div class="col">Accounts</div>
        <div class="col">Authorized signatory</div>
    </div>
</body>
</html>
