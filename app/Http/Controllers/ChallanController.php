<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\Dispatch;
use Illuminate\Http\Request;

/**
 * Read-only listing of dispatch challans. A challan IS a Dispatch's
 * challan_no + shipment details (DispatchService::vehicleOut() issues it
 * once the shipment's status moves to "dispatched", and dispatches.print/pdf
 * already render the challan document) — this controller just gives
 * Accounts/Admin a dedicated, filterable list of them without duplicating
 * that document.
 *
 * Role-based access refactor: Dispatch is deliberately excluded — Challans
 * is not in Dispatch's current menu, even though Dispatch users are the
 * ones who issue challans by creating dispatches.
 */
class ChallanController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeView($request);

        $challans = $this->filtered($request)
            ->with(['lines.dealer', 'lines.farmer', 'vehicle'])
            ->latest('dispatch_date')
            ->paginate(15)
            ->withQueryString();

        $dealers = Dealer::orderBy('dealer_name')->get(['id', 'dealer_name']);

        return view('challans.index', compact('challans', 'dealers'));
    }

    public function export(Request $request)
    {
        $this->authorizeView($request);

        $challans = $this->filtered($request)->with(['lines.dealer', 'lines.farmer', 'vehicle'])->latest('dispatch_date')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="challans.csv"',
        ];

        return response()->streamDownload(function () use ($challans) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Challan No', 'Dispatch No', 'Dealers', 'Vehicle', 'Dispatch Date', 'Status']);
            foreach ($challans as $dispatch) {
                fputcsv($out, [
                    $dispatch->challan_no,
                    $dispatch->dispatch_no,
                    $dispatch->lines->pluck('dealer.dealer_name')->filter()->unique()->implode(', '),
                    $dispatch->vehicle?->vehicle_no,
                    $dispatch->dispatch_date?->format('Y-m-d'),
                    $dispatch->status,
                ]);
            }
            fclose($out);
        }, 'challans.csv', $headers);
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()->hasRole(['super-admin', 'admin', 'accounts']) || $request->user()->hasPermission('challans.view'),
            403
        );
    }

    /**
     * Shared search/filter/role-scope query builder, reused by index() and export().
     */
    private function filtered(Request $request)
    {
        $search = $request->string('search')->toString();

        return Dispatch::query()
            ->whereNotNull('challan_no')
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('challan_no', 'like', "%{$search}%")
                ->orWhere('dispatch_no', 'like', "%{$search}%")
                ->orWhereHas('lines.dealer', fn ($d) => $d->where('dealer_name', 'like', "%{$search}%"))))
            ->when($request->filled('dealer'), fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('dealer_id', $request->input('dealer'))))
            ->when($request->user()->hasRole('dealer'), fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('dealer_id', $request->user()->dealer_id)));
    }
}
