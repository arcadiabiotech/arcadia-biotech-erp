<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Challan {{ $dispatch->challan_no ?? $dispatch->dispatch_no }}</title>
    <style>
        /* A5, 3mm margins (near full-bleed — uses the whole A5 sheet), single
           page — dompdf and browser print both honor @page. Sizing throughout
           this file is tuned for A5's canvas (148 x 210mm) — re-check the page
           count (see dompdf getCanvas()->get_page_count()) after touching any
           font-size/padding here. */
        @page { size: A5; margin: 3mm; }
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            color: #1e293b;
            font-size: 12px;
            line-height: 1.08;
            margin: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table { border-collapse: separate; border-spacing: 0; }
        .items, .items th, .items td { border-collapse: collapse; }
        .full { width: 100%; }

        /* ---------- Header ---------- */
        .header {
            width: 100%;
            background: #0b3d2e;
            color: #ffffff;
            border-radius: 8px;
            padding: 5px 8px;
            margin-bottom: 4px;
        }
        .header .brand-cell { width: 60%; vertical-align: top; }
        .header .meta-cell { width: 40%; vertical-align: top; text-align: right; }
        .brand-row { width: 100%; }
        .brand-row .logo-cell { width: 34px; vertical-align: top; }
        .logo-badge {
            width: 30px; height: 30px; border-radius: 50%;
            background: #ffffff; color: #0b3d2e;
            text-align: center; vertical-align: middle;
            font-size: 13px; font-weight: bold;
        }
        .logo-img { width: 30px; height: 30px; border-radius: 50%; background: #fff; }
        .company-name { font-size: 17px; font-weight: bold; letter-spacing: 0.3px; }
        .company-tagline { font-size: 10px; color: #cfe7dc; margin-top: 1px; }
        .company-meta { font-size: 9.5px; color: #dff2e8; margin-top: 2px; }
        .doc-title {
            display: inline-block;
            font-size: 14px; font-weight: bold; letter-spacing: 0.8px;
            background: rgba(255,255,255,0.12);
            padding: 2px 6px; border-radius: 5px;
            margin-bottom: 3px;
        }
        .meta-line { font-size: 10px; color: #eafff5; margin-top: 0.5px; }
        .meta-line strong { color: #ffffff; }
        .qr-box {
            margin-top: 2px; margin-left: auto;
            width: 46px; height: 46px;
            background: #ffffff; border-radius: 6px;
            text-align: center; vertical-align: middle;
            padding: 2px;
        }
        .qr-caption { font-size: 8px; color: #dff2e8; margin-top: 1px; }

        /* ---------- Section boxes ---------- */
        .box {
            border: 1px solid #dbe4df;
            border-radius: 6px;
            padding: 3px 5px;
            vertical-align: top;
        }
        .box-title {
            font-size: 10px; font-weight: bold; text-transform: uppercase;
            letter-spacing: 0.3px; color: #0b3d2e;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 1px; margin-bottom: 1px;
        }
        .kv { width: 100%; }
        .kv td { padding: 0; font-size: 10.6px; vertical-align: top; }
        .kv td.k { color: #64748b; width: 42%; }
        .kv td.v { color: #1e293b; font-weight: bold; text-align: right; }
        .two-col { width: 100%; margin-top: 3px; }
        .two-col td { width: 50%; padding: 0; }
        .two-col td.left-pad { padding-right: 3px; }
        .two-col td.right-pad { padding-left: 3px; }

        /* ---------- Item table ---------- */
        .items { width: 100%; margin-top: 3px; border: 1px solid #dbe4df; border-radius: 6px; overflow: hidden; }
        .items th {
            background: #0b3d2e; color: #fff; font-size: 9.5px; text-transform: uppercase;
            letter-spacing: 0.2px; padding: 1.5px 4px; text-align: left;
        }
        .items td { padding: 1.5px 4px; font-size: 10.6px; border-top: 1px solid #eef2f0; }
        .items td.num, .items th.num { text-align: right; }
        .items tfoot td { font-weight: bold; background: #f4f9f6; border-top: 1px solid #dbe4df; }

        /* ---------- Summary ---------- */
        .summary { width: 100%; margin-top: 3px; }
        .summary td { width: 50%; vertical-align: top; }
        .summary td.left-pad { padding-right: 3px; }
        .summary td.right-pad { padding-left: 3px; }
        .sign-line-label { font-size: 9.5px; color: #64748b; }
        .sign-line-value { font-size: 10.8px; font-weight: bold; border-bottom: 1px solid #cbd5e1; display: block; min-height: 12px; margin-top: 0.5px; }

        /* ---------- Barcode ---------- */
        .barcode-wrap { text-align: center; margin-top: 2px; }
        .barcode-wrap svg { height: 16px; }
        .barcode-caption { font-size: 8.6px; letter-spacing: 1px; color: #64748b; margin-top: 0.5px; }

        /* ---------- Terms ---------- */
        .terms { margin-top: 3px; border-top: 1px dashed #cbd5e1; padding-top: 2px; }
        .terms-title { font-size: 10px; font-weight: bold; color: #0b3d2e; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 1px; }
        .terms table { width: 100%; }
        .terms td { width: 50%; vertical-align: top; padding: 0; }
        .terms td.right-pad { padding-left: 5px; }
        .terms ol { margin: 0; padding-left: 10px; }
        .terms li { font-size: 8.8px; color: #475569; margin-bottom: 0; }

        /* ---------- Footer signatures ---------- */
        .footer { width: 100%; margin-top: 22px; }
        .footer td { width: 25%; text-align: center; font-size: 9px; color: #64748b; padding-top: 50px; border-top: 1px solid #94a3b8; }

        .public-note { margin-top: 2px; font-size: 8.2px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>

    {{-- ============ HEADER ============ --}}
    <table class="header">
        <tr>
            <td class="brand-cell">
                <table class="brand-row">
                    <tr>
                        <td class="logo-cell">
                            @php($logoPath = public_path(config('company.logo')))
                            @if(is_file($logoPath))
                                <img src="{{ $logoPath }}" class="logo-img" alt="Logo">
                            @else
                                {{-- No uploaded logo yet — fall back to a generated monogram badge --}}
                                <table style="width:30px;height:30px;"><tr><td class="logo-badge">{{ strtoupper(substr(config('company.name'), 0, 1)).strtoupper(substr(strrchr(config('company.name'), ' ') ?: config('company.name'), 1, 1)) }}</td></tr></table>
                            @endif
                        </td>
                        <td>
                            <div class="company-name">{{ strtoupper(config('company.name')) }}</div>
                            <div class="company-tagline">{{ config('company.tagline') }}</div>
                        </td>
                    </tr>
                </table>
                <div class="company-meta">
                    {{ config('company.address') }}
                    @if(config('company.gst_number')) &nbsp;·&nbsp; GST: {{ config('company.gst_number') }} @endif
                    <br>
                    Mobile: {{ config('company.mobile') }} &nbsp;·&nbsp; Email: {{ config('company.email') }} &nbsp;·&nbsp; {{ config('company.website') }}
                </div>
            </td>
            <td class="meta-cell">
                <div class="doc-title">DELIVERY CHALLAN</div>
                <div class="meta-line"><strong>DC No:</strong> {{ $dispatch->challan_no ?? 'Not yet issued' }}</div>
                <div class="meta-line"><strong>Dispatch No:</strong> {{ $dispatch->dispatch_no }}</div>
                <div class="meta-line"><strong>Plan No:</strong> {{ $dispatch->vehicleAssignment?->dispatchPlan?->plan_no ?? '—' }}</div>
                <div class="meta-line"><strong>Date:</strong> {{ $dispatch->dispatch_date?->format('d M Y') }}</div>
                <div class="meta-line"><strong>Print time:</strong> {{ now()->format('d M Y, h:i A') }}</div>

                @if($dispatch->challan_no)
                    <table style="margin-left:auto;"><tr><td class="qr-box">
                        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(44)->margin(0)->generate(
                            \Illuminate\Support\Facades\URL::temporarySignedRoute('challans.public', now()->addDays(60), ['dispatch' => $dispatch->id])
                        ) !!}
                    </td></tr></table>
                    <div class="qr-caption">Scan to verify</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ============ TRANSPORT DETAILS ============ --}}
    <div class="box">
        <div class="box-title">Transport Details</div>
        <table class="kv">
            <tr><td class="k">Vehicle number</td><td class="v">{{ $dispatch->vehicle?->vehicle_no ?? '—' }}</td></tr>
            <tr><td class="k">Driver name</td><td class="v">{{ $dispatch->driver_name ?? '—' }}</td></tr>
            <tr><td class="k">Driver mobile</td><td class="v">{{ $dispatch->driver_mobile ?? '—' }}</td></tr>
            <tr><td class="k">Transport name</td><td class="v">{{ $dispatch->vehicle?->transport_company ?? '—' }}</td></tr>
            <tr><td class="k">LR number</td><td class="v">{{ $dispatch->lr_number ?? '—' }}</td></tr>
            <tr><td class="k">Dispatch date</td><td class="v">{{ $dispatch->dispatch_date?->format('d M Y') }}</td></tr>
            <tr><td class="k">Loading time</td><td class="v">{{ $dispatch->loaded_at?->format('d M Y, h:i A') ?? '—' }}</td></tr>
        </table>
    </div>

    {{-- ============ DEALER-WISE ITEMS ============ --}}
    @foreach($dispatch->lines->groupBy('dealer_id') as $dealerLines)
        @php($dealer = $dealerLines->first()->dealer)
        <div class="box" style="margin-top:3px;">
            <div class="box-title">{{ $dealer?->dealer_name }}@if($dealer?->dealer_code) ({{ $dealer->dealer_code }}) @endif — {{ $dealer?->mobile }}</div>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:22%">Farmer</th>
                        <th style="width:16%">Booking</th>
                        <th style="width:14%">Variety</th>
                        <th style="width:12%">Batch</th>
                        <th class="num" style="width:12%">Qty</th>
                        <th class="num" style="width:12%">Rate</th>
                        <th class="num" style="width:12%">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dealerLines as $line)
                        <tr>
                            <td>{{ $line->farmer?->farmer_name }}</td>
                            <td>{{ $line->booking->booking_no }}</td>
                            <td>{{ $line->booking->variety }}</td>
                            <td>{{ $line->batch_number ?? '—' }}</td>
                            <td class="num">{{ number_format($line->total_qty) }}</td>
                            <td class="num">{{ number_format($line->booking->plant_rate ?? 0, 2) }}</td>
                            <td class="num">{{ number_format($line->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Dealer subtotal</td>
                        <td class="num">{{ number_format($dealerLines->sum('total_qty')) }}</td>
                        <td></td>
                        <td class="num">{{ number_format($dealerLines->sum('amount'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach

    {{-- ============ SUMMARY ============ --}}
    <table class="summary">
        <tr>
            <td class="left-pad">
                <div class="box">
                    <div class="box-title">Summary</div>
                    <table class="kv">
                        <tr><td class="k">Total quantity</td><td class="v">{{ number_format($dispatch->total_qty) }} plants</td></tr>
                        <tr><td class="k">Total amount</td><td class="v">₹{{ number_format($dispatch->total_amount, 2) }}</td></tr>
                    </table>
                    @if($dispatch->remarks)
                        <div style="margin-top:4px;">
                            <div class="sign-line-label">Remarks</div>
                            <div style="font-size:7.4px;">{{ $dispatch->remarks }}</div>
                        </div>
                    @endif
                    @if($dispatch->challan_no)
                        {{-- Second, independent verification mark: a 1D barcode of the DC
                             number for handheld scanners at the dealer/warehouse gate —
                             the QR code up top is for a phone camera to open the ERP page. --}}
                        <div class="barcode-wrap">
                            {!! (new \Picqer\Barcode\BarcodeGeneratorSVG())->getBarcode($dispatch->challan_no, \Picqer\Barcode\BarcodeGeneratorSVG::TYPE_CODE_128, 1.3, 22) !!}
                            <div class="barcode-caption">{{ $dispatch->challan_no }}</div>
                        </div>
                    @endif
                </div>
            </td>
            <td class="right-pad">
                <div class="box">
                    <div class="box-title">Verification</div>
                    <div style="margin-bottom:5px;">
                        <span class="sign-line-label">Prepared by</span>
                        <span class="sign-line-value">{{ $dispatch->createdBy?->name ?? '—' }}</span>
                    </div>
                    <div style="margin-bottom:5px;">
                        <span class="sign-line-label">Checked by</span>
                        <span class="sign-line-value">&nbsp;</span>
                    </div>
                    <div>
                        <span class="sign-line-label">Authorized by</span>
                        <span class="sign-line-value">&nbsp;</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ============ TERMS & CONDITIONS ============ --}}
    <div class="terms">
        <div class="terms-title">Terms &amp; Conditions</div>
        <table>
            <tr>
                <td>
                    <ol>
                        <li>All plants must be checked before taking delivery.</li>
                        <li>No complaint regarding quantity or quality will be accepted after delivery.</li>
                        <li>Plants should be transported carefully.</li>
                        <li>Keep plants away from direct sunlight.</li>
                        <li>Water plants immediately after unloading.</li>
                    </ol>
                </td>
                <td class="right-pad">
                    <ol start="6">
                        <li>Company is not responsible for damage caused due to improper transportation.</li>
                        <li>Any complaint should be made within 24 hours.</li>
                        <li>Plants once sold will not be taken back.</li>
                        <li>Subject to {{ config('company.jurisdiction') }} Jurisdiction.</li>
                        <li>This is a computer generated challan.</li>
                    </ol>
                </td>
            </tr>
        </table>
    </div>

    {{-- ============ FOOTER SIGNATURES ============ --}}
    <table class="footer">
        <tr>
            <td>Receiver Signature</td>
            <td>Driver Signature</td>
            <td>Dealer Signature</td>
            <td>Authorized Signature</td>
        </tr>
    </table>

    @if(!empty($isPublic))
        <div class="public-note">Verified via {{ config('company.name') }} ERP — scanned from the challan QR code.</div>
    @endif

</body>
</html>
