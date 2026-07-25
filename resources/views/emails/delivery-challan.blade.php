@php($dcNo = $dispatch->challan_no ?? $dispatch->dispatch_no)
@component('mail::message')
# Delivery Challan {{ $dcNo }}

Dear {{ $dispatch->lines->first()?->dealer?->dealer_name ?? 'Sir/Madam' }},

Please find attached the delivery challan for the shipment below.

| | |
|---|---|
| **Dealers** | {{ $dispatch->lines->pluck('dealer.dealer_name')->filter()->unique()->implode(', ') }} |
| **Bookings** | {{ $dispatch->lines->pluck('booking.booking_no')->filter()->implode(', ') }} |
| **Total plants** | {{ number_format($dispatch->total_qty) }} |
| **Vehicle** | {{ $dispatch->vehicle?->vehicle_no ?? '—' }} |

@component('mail::button', ['url' => route('login')])
Open in ERP
@endcomponent

This is a computer generated email from {{ config('company.name') }}.

Thanks,<br>
{{ config('company.name') }}
@endcomponent
