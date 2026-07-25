<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\Dispatch;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Transport Cost Report (Step 7A) — vehicle-wise, dealer-wise and monthly
 * transport cost rollups, plus a filterable dispatch-wise detail list.
 * Modeled directly on CrateReturnReportController: only dispatches whose
 * transport cost has actually been recorded (total_transport_expense not
 * null, i.e. the vehicle has been marked returned) are included — a
 * dispatch still in transit has nothing to report yet.
 */
class TransportReportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin', 'dispatch']), 403);

        $dispatches = $this->filtered($request);

        return view('reports.transport-cost', [
            'dispatches' => $dispatches,
            'vehicles' => Vehicle::orderBy('vehicle_no')->get(['id', 'vehicle_no']),
            'dealers' => Dealer::orderBy('dealer_name')->get(['id', 'dealer_name']),
            'vehicleSummary' => $this->summarizeByVehicle($dispatches),
            'dealerSummary' => $this->summarizeByDealer($dispatches),
            'monthlySummary' => $this->summarizeByMonth($dispatches),
            'overall' => $this->summarizeOverall($dispatches),
        ]);
    }

    public function csv(Request $request)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin', 'dispatch']), 403);

        $dispatches = $this->filtered($request);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="transport-cost-report.csv"',
        ];

        return response()->streamDownload(function () use ($dispatches) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Dispatch No', 'Vehicle', 'Return Date', 'Odometer Start', 'Odometer End', 'Total KM', 'Cost/KM', 'Transport Cost', 'Driver Allowance', 'Toll Charges', 'Other Expenses', 'Total Expense', 'Total Plants', 'Cost/Plant']);
            foreach ($dispatches as $dispatch) {
                fputcsv($out, [
                    $dispatch->dispatch_no,
                    $dispatch->vehicle?->vehicle_no,
                    $dispatch->vehicle_returned_at?->format('Y-m-d'),
                    $dispatch->odometer_start,
                    $dispatch->odometer_end,
                    $dispatch->total_km,
                    $dispatch->cost_per_km,
                    $dispatch->transport_cost,
                    $dispatch->driver_allowance,
                    $dispatch->toll_charges,
                    $dispatch->other_expenses,
                    $dispatch->total_transport_expense,
                    $dispatch->total_qty,
                    $dispatch->cost_per_plant,
                ]);
            }
            fclose($out);
        }, 'transport-cost-report.csv', $headers);
    }

    private function filtered(Request $request): Collection
    {
        return Dispatch::whereNotNull('total_transport_expense')
            ->with(['vehicle', 'lines.dealer'])
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->input('vehicle_id')))
            ->when($request->filled('dealer'), fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('dealer_id', $request->input('dealer'))))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('vehicle_returned_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('vehicle_returned_at', '<=', $request->input('date_to')))
            ->latest('vehicle_returned_at')
            ->get();
    }

    private function summarizeByVehicle(Collection $dispatches): Collection
    {
        return $dispatches->groupBy('vehicle_id')
            ->map(fn (Collection $group) => [
                'vehicle' => $group->first()->vehicle,
                'trips' => $group->count(),
                'total_km' => (int) $group->sum('total_km'),
                'transport_cost' => round((float) $group->sum('transport_cost'), 2),
                'total_expense' => round((float) $group->sum('total_transport_expense'), 2),
            ])
            ->sortByDesc('total_expense')
            ->values();
    }

    private function summarizeByDealer(Collection $dispatches): Collection
    {
        return $dispatches->flatMap(fn (Dispatch $dispatch) => $dispatch->dealer_transport_allocation)
            ->groupBy('dealer_id')
            ->map(fn (Collection $group) => [
                'dealer' => $group->first()['dealer'],
                'qty' => $group->sum('qty'),
                'allocated_cost' => round((float) $group->sum('allocated_cost'), 2),
            ])
            ->sortByDesc('allocated_cost')
            ->values();
    }

    private function summarizeByMonth(Collection $dispatches): Collection
    {
        return $dispatches->filter(fn (Dispatch $d) => $d->vehicle_returned_at !== null)
            ->groupBy(fn (Dispatch $d) => $d->vehicle_returned_at->format('Y-m'))
            ->map(fn (Collection $group, string $month) => [
                'month' => $month,
                'total_km' => (int) $group->sum('total_km'),
                'total_expense' => round((float) $group->sum('total_transport_expense'), 2),
            ])
            ->sortKeysDesc()
            ->values();
    }

    private function summarizeOverall(Collection $dispatches): array
    {
        $count = $dispatches->count();
        $totalKm = (int) $dispatches->sum('total_km');
        $totalExpense = round((float) $dispatches->sum('total_transport_expense'), 2);
        $totalPlants = (int) $dispatches->sum('total_qty');

        return [
            'trips' => $count,
            'total_km' => $totalKm,
            'total_expense' => $totalExpense,
            'avg_cost_per_dispatch' => $count > 0 ? round($totalExpense / $count, 2) : 0,
            'avg_cost_per_plant' => $totalPlants > 0 ? round($totalExpense / $totalPlants, 2) : 0,
        ];
    }
}
