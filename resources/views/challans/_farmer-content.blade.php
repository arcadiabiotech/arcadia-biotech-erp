{{-- Farmer Challan body — shared by challans.farmer (standalone),
     challans.farmers-all (all farmers combined) and challans.print-all
     (dealer + every farmer combined). Expects $challan (type=farmer),
     $dispatch, $lines (only this farmer's DispatchLine rows) and optional
     $isPublic. Only this farmer's own quantity is ever shown here — never
     the dispatch/dealer total. --}}

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
            <div class="doc-title">FARMER CHALLAN</div>
            <div class="meta-line"><strong>FC No:</strong> {{ $challan->challan_no }}</div>
            <div class="meta-line"><strong>Dispatch No:</strong> {{ $dispatch->dispatch_no }}</div>
            <div class="meta-line"><strong>Date:</strong> {{ $dispatch->dispatch_date?->format('d M Y') }}</div>

            <table style="margin-left:auto;"><tr><td class="qr-box">
                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(44)->margin(0)->generate(
                    \Illuminate\Support\Facades\URL::temporarySignedRoute('challan-docs.public', now()->addDays(60), ['challan' => $challan->id])
                ) !!}
            </td></tr></table>
            <div class="qr-caption">Scan to verify</div>
        </td>
    </tr>
</table>

{{-- ============ FARMER + DEALER DETAILS ============ --}}
<table class="two-col">
    <tr>
        <td class="left-pad">
            <div class="box">
                <div class="box-title">Farmer Details</div>
                <table class="kv">
                    <tr><td class="k">Farmer name</td><td class="v">{{ $challan->farmer->farmer_name }}</td></tr>
                    <tr><td class="k">Dealer name</td><td class="v">{{ $challan->dealer->dealer_name }}</td></tr>
                    <tr><td class="k">Village</td><td class="v">{{ $challan->farmer->village?->name ?? '—' }}</td></tr>
                    <tr><td class="k">Taluka</td><td class="v">{{ $challan->farmer->taluka?->name ?? '—' }}</td></tr>
                    <tr><td class="k">District</td><td class="v">{{ $challan->farmer->district?->name ?? '—' }}</td></tr>
                    <tr><td class="k">Mobile</td><td class="v">{{ $challan->farmer->mobile ?? '—' }}</td></tr>
                </table>
            </div>
        </td>
        <td class="right-pad">
            <div class="box">
                <div class="box-title">Vehicle Details</div>
                <table class="kv">
                    <tr><td class="k">Vehicle number</td><td class="v">{{ $dispatch->vehicle?->vehicle_no ?? '—' }}</td></tr>
                    <tr><td class="k">Driver name</td><td class="v">{{ $dispatch->driver_name ?? '—' }}</td></tr>
                    <tr><td class="k">Driver mobile</td><td class="v">{{ $dispatch->driver_mobile ?? '—' }}</td></tr>
                    <tr><td class="k">Dispatch date</td><td class="v">{{ $dispatch->dispatch_date?->format('d M Y') }}</td></tr>
                    <tr><td class="k">Plan No</td><td class="v">{{ $dispatch->vehicleAssignment?->dispatchPlan?->plan_no ?? '—' }}</td></tr>
                    <tr><td class="k">Loading time</td><td class="v">{{ $dispatch->loaded_at?->format('d M Y, h:i A') ?? '—' }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- ============ THIS FARMER'S QUANTITY ONLY ============ --}}
<table class="items">
    <thead>
        <tr>
            <th style="width:40%">Variety</th>
            <th style="width:30%">Batch</th>
            <th class="num" style="width:30%">Quantity</th>
        </tr>
    </thead>
    <tbody>
        @foreach($lines as $line)
            <tr>
                <td>{{ $line->booking?->variety }}</td>
                <td>{{ $line->batch_number ?? '—' }}</td>
                <td class="num">{{ number_format($line->total_qty) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">Total Plants (this farmer only)</td>
            <td class="num">{{ number_format($lines->sum('total_qty')) }}</td>
        </tr>
    </tfoot>
</table>

{{-- ============ VERIFICATION ============ --}}
<table class="summary">
    <tr>
        <td class="left-pad">
            <div class="box">
                <div class="box-title">Summary</div>
                <table class="kv">
                    <tr><td class="k">Total quantity</td><td class="v">{{ number_format($lines->sum('total_qty')) }} plants</td></tr>
                    <tr><td class="k">Master challan</td><td class="v">{{ $challan->parentChallan?->challan_no ?? '—' }}</td></tr>
                </table>
                <div class="barcode-wrap">
                    {!! (new \Picqer\Barcode\BarcodeGeneratorSVG())->getBarcode($challan->challan_no, \Picqer\Barcode\BarcodeGeneratorSVG::TYPE_CODE_128, 1.3, 22) !!}
                    <div class="barcode-caption">{{ $challan->challan_no }}</div>
                </div>
            </div>
        </td>
        <td class="right-pad">
            <div class="box">
                <div class="box-title">Prepared By</div>
                <div>
                    <span class="sign-line-label">Prepared by</span>
                    <span class="sign-line-value">{{ $challan->createdBy?->name ?? '—' }}</span>
                </div>
            </div>
        </td>
    </tr>
</table>

@include('challans._terms')
@include('challans._signatures')

@if(!empty($isPublic))
    <div class="public-note">Verified via {{ config('company.name') }} ERP — scanned from the challan QR code.</div>
@endif
