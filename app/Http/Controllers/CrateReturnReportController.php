<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\DispatchLine;
use App\Models\Farmer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Crate Return Report — reconciles crates sent vs returned vs damaged.
 * Reported per booking line (see DispatchLine::crate_count/pending_crates),
 * not per dispatch, since a multi-dealer Dispatch can't be reconciled as a
 * single number without misattributing a return to the wrong dealer.
 */
class CrateReturnReportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin', 'dispatch']), 403);

        $lines = $this->filtered($request);

        return view('reports.crate-returns', [
            'lines' => $lines,
            'dealers' => Dealer::orderBy('dealer_name')->get(['id', 'dealer_name']),
            'farmers' => Farmer::orderBy('farmer_name')->get(['id', 'farmer_name']),
            'summary' => $this->summarize($lines),
        ]);
    }

    public function pdf(Request $request)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin', 'dispatch']), 403);

        $lines = $this->filtered($request);

        return Pdf::loadView('reports.crate-returns-pdf', [
            'lines' => $lines,
            'summary' => $this->summarize($lines),
        ])->download('crate-return-report-'.now()->format('Ymd').'.pdf');
    }

    public function csv(Request $request)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin', 'dispatch']), 403);

        $lines = $this->filtered($request);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="crate-return-report.csv"',
        ];

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Dispatch No', 'Vehicle', 'Dealer', 'Farmer', 'Return Date', 'Total Crates', 'Returned', 'Damaged', 'Pending', 'Status']);
            foreach ($lines as $line) {
                fputcsv($out, [
                    $line->dispatch?->dispatch_no,
                    $line->dispatch?->vehicle?->vehicle_no,
                    $line->dealer?->dealer_name,
                    $line->farmer?->farmer_name,
                    $line->dispatch?->vehicle_returned_at?->format('Y-m-d'),
                    $line->crate_count,
                    (float) ($line->crates_returned ?? 0),
                    (float) ($line->damage_qty ?? 0),
                    $line->pending_crates,
                    $line->pending_crates > 0 ? 'Pending' : 'Returned',
                ]);
            }
            fclose($out);
        }, 'crate-return-report.csv', $headers);
    }

    /**
     * Filtered per-line. crate_count/pending_crates are computed accessors
     * (not DB columns, see DispatchLine), so the Pending/Returned status
     * filter is applied in PHP after the indexed-column query — same
     * pattern as DashboardController::pendingVehicleReturns().
     */
    private function filtered(Request $request): Collection
    {
        $lines = DispatchLine::whereNotNull('qty_per_crate')
            ->with(['dispatch.vehicle', 'dealer', 'farmer', 'booking'])
            ->when($request->filled('dealer'), fn ($q) => $q->where('dealer_id', $request->input('dealer')))
            ->when($request->filled('farmer'), fn ($q) => $q->where('farmer_id', $request->input('farmer')))
            ->when($request->filled('dispatch_no'), fn ($q) => $q->whereHas(
                'dispatch',
                fn ($d) => $d->where('dispatch_no', 'like', '%'.$request->input('dispatch_no').'%')
            ))
            ->when($request->filled('date_from'), fn ($q) => $q->whereHas(
                'dispatch',
                fn ($d) => $d->whereDate('vehicle_returned_at', '>=', $request->input('date_from'))
            ))
            ->when($request->filled('date_to'), fn ($q) => $q->whereHas(
                'dispatch',
                fn ($d) => $d->whereDate('vehicle_returned_at', '<=', $request->input('date_to'))
            ))
            ->latest('id')
            ->get();

        return match ($request->input('status')) {
            'pending' => $lines->filter(fn (DispatchLine $line) => $line->pending_crates > 0)->values(),
            'completed' => $lines->filter(fn (DispatchLine $line) => $line->pending_crates <= 0)->values(),
            default => $lines,
        };
    }

    private function summarize(Collection $lines): array
    {
        return [
            'total' => round((float) $lines->sum('crate_count'), 2),
            'returned' => round((float) $lines->sum(fn (DispatchLine $l) => (float) ($l->crates_returned ?? 0)), 2),
            'damaged' => round((float) $lines->sum(fn (DispatchLine $l) => (float) ($l->damage_qty ?? 0)), 2),
            'pending' => round((float) $lines->sum('pending_crates'), 2),
        ];
    }
}
