<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Booking {{ $booking->booking_no }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1e293b; font-size: 13px; margin: 32px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e2e8f0; }
        th { color: #64748b; font-size: 11px; text-transform: uppercase; }
        .amount-table td:last-child, .amount-table th:last-child { text-align: right; }
        .total-row td { font-weight: bold; border-top: 2px solid #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 16px; }
        .header .col { display: table-cell; vertical-align: top; width: 50%; }
        .signature { display: table; width: 100%; margin-top: 60px; }
        .signature .col { display: table-cell; width: 33%; padding-top: 30px; border-top: 1px solid #94a3b8; text-align: center; font-size: 11px; color: #64748b; }
        @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="col">
            <h1>Arcadia Biotech</h1>
            <p class="muted">Banana Tissue Culture · Booking Slip</p>
        </div>
        <div class="col" style="text-align:right">
            <p><strong>{{ $booking->booking_no }}</strong></p>
            <p class="muted">{{ $booking->booking_date->format('d M Y') }}</p>
        </div>
    </div>

    <table>
        <tr><th style="width:25%">Dealer</th><td>{{ $booking->dealer?->dealer_name }} ({{ $booking->dealer?->dealer_code }})</td></tr>
        <tr><th>Farmer</th><td>{{ $booking->farmer?->farmer_name }} ({{ $booking->farmer?->farmer_code }})</td></tr>
        <tr><th>Variety</th><td>{{ $booking->variety }}</td></tr>
        <tr><th>Plant quantity</th><td>{{ number_format($booking->plant_qty) }}</td></tr>
        <tr><th>Status</th><td>Approval: {{ ucfirst($booking->approval_status) }} · Payment: {{ ucfirst($booking->payment_status) }}</td></tr>
    </table>

    <table class="amount-table">
        <tr><th>Description</th><th>Amount (₹)</th></tr>
        <tr><td>Plant rate × quantity</td><td>{{ number_format($booking->plant_rate, 2) }} × {{ $booking->plant_qty }}</td></tr>
        <tr><td>Booking amount</td><td>{{ number_format($booking->booking_amount, 2) }}</td></tr>
        <tr><td>Discount</td><td>{{ number_format($booking->discount, 2) }}</td></tr>
        <tr><td>Advance received</td><td>{{ number_format($booking->advance_amount, 2) }}</td></tr>
        <tr class="total-row"><td>Balance payable</td><td>{{ number_format($booking->balance_amount, 2) }}</td></tr>
    </table>

    @if($booking->remarks)
        <p class="muted" style="margin-top:16px"><strong>Remarks:</strong> {{ $booking->remarks }}</p>
    @endif

    <div class="signature">
        <div class="col">Farmer signature</div>
        <div class="col">Dealer signature</div>
        <div class="col">Authorized signatory</div>
    </div>
</body>
</html>
