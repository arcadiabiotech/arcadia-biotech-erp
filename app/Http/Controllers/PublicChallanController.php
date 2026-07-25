<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use Illuminate\Http\Request;

/**
 * The QR code printed on a challan encodes a signed link to this
 * controller — not raw text — so scanning it with a phone camera actually
 * opens the challan in the ERP instead of just showing unstructured text.
 * No auth guard here on purpose: the person scanning (farmer, dealer,
 * transporter) is very likely not an ERP user. Laravel's `signed`
 * middleware is what keeps this safe — only a URL minted by
 * URL::temporarySignedRoute() (see the challan Blade) will pass; anyone
 * guessing a dispatch id without the signature gets a 403.
 */
class PublicChallanController extends Controller
{
    public function show(Request $request, Dispatch $dispatch)
    {
        abort_unless($dispatch->challan_no, 404);

        $dispatch->load(['lines.booking', 'lines.dealer', 'lines.farmer.village', 'lines.farmer.taluka', 'lines.farmer.district', 'lines.farmer.state', 'vehicle', 'createdBy']);

        return view('dispatches.print', ['dispatch' => $dispatch, 'isPublic' => true]);
    }
}
