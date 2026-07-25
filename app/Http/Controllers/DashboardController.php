<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Dispatch;
use App\Models\DispatchLine;
use App\Models\Farmer;
use App\Models\Payment;
use App\Models\Rating;
use Illuminate\Http\Request;

/**
 * Role-based dashboard refactor: every role used to land on the same
 * system-wide view (all dealers, all bookings, all activity), regardless of
 * what they were actually allowed to touch elsewhere in the app. This
 * controller picks a role-specific view and scopes its data the same way
 * the rest of the app already does (BookingController::filtered(),
 * DispatchController::filtered(), etc.) — nobody sees another tenant's
 * numbers on their dashboard anymore.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $roleName = $user->role?->name;

        return match ($roleName) {
            'super-admin', 'admin' => $this->admin(),
            'marketing' => $this->marketing($user),
            'dealer' => $this->dealer($user),
            'accounts' => $this->accounts(),
            'dispatch' => $this->dispatch(),
            // Lab Technician and Supervisor's only menu items are Dashboard +
            // Lab Operations (see navigation.blade.php) — both should land
            // on the same Lab Ops Dashboard rather than the generic
            // "no module assigned" fallback.
            'lab-technician', 'supervisor' => redirect()->route('lab.dashboard'),
            default => view('dashboard.default'),
        };
    }

    private function admin()
    {
        $stats = [
            ['label' => 'Total Dealers', 'value' => Dealer::count(), 'color' => 'blue', 'icon' => 'M4 7h16v13H4z M8 7V4h8v3 M4 12h16'],
            ['label' => 'Total Farmers', 'value' => Farmer::count(), 'color' => 'emerald', 'icon' => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8z'],
            ['label' => 'Total Bookings', 'value' => Booking::count(), 'color' => 'violet', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2 M9 5a3 3 0 006 0'],
            ['label' => 'Total Dispatch', 'value' => Dispatch::count(), 'color' => 'amber', 'icon' => 'M3 7h11v10H3z M14 10h4l3 3v4h-7v-7z'],
        ];

        $recentBookings = Booking::with(['dealer', 'farmer'])->latest()->limit(5)->get();
        $latestActivities = ActivityLog::with('user')->latest()->limit(6)->get();

        $topRatedDealer = $this->topRated(Dealer::class);
        $topRatedFarmer = $this->topRated(Farmer::class);
        $pendingReturns = $this->pendingVehicleReturns();

        $stats = array_merge($stats, $this->transportStats());

        return view('dashboard.admin', compact('stats', 'recentBookings', 'latestActivities', 'topRatedDealer', 'topRatedFarmer', 'pendingReturns'));
    }

    /**
     * Step 7A dashboard cards: Vehicles on Route, Total KM Today, Transport
     * Cost Today, Avg Cost/Plant (all-time) and Monthly Transport Expense.
     * Shared by admin() and dispatch() — both dashboards surface vehicle
     * ops. Keyed off vehicle_returned_at (the day a trip's cost is
     * finalized by markVehicleReturned()) rather than odometer_end capture
     * time, since there's no separate timestamp for that. Reuses the same
     * {label, value, color, icon} shape the existing $stats cards already
     * render through — 'color' is intentionally limited to blue/emerald/
     * violet/amber, the only colors both dashboard Blade files' @class map
     * actually handles.
     */
    private function transportStats(): array
    {
        $vehiclesOnRoute = Dispatch::whereIn('status', ['loading', 'dispatched'])->distinct('vehicle_id')->count('vehicle_id');

        $returnedToday = Dispatch::whereDate('vehicle_returned_at', today())->whereNotNull('total_transport_expense');
        $kmToday = (int) (clone $returnedToday)->sum('total_km');
        $costToday = round((float) (clone $returnedToday)->sum('total_transport_expense'), 2);

        $monthlyExpense = round((float) Dispatch::whereNotNull('total_transport_expense')
            ->whereBetween('vehicle_returned_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total_transport_expense'), 2);

        $totalExpense = (float) Dispatch::whereNotNull('total_transport_expense')->sum('total_transport_expense');
        $totalPlants = (float) DispatchLine::whereHas('dispatch', fn ($q) => $q->whereNotNull('total_transport_expense'))
            ->selectRaw('SUM(dispatch_qty + extra_qty) as qty')->value('qty');
        $avgCostPerPlant = $totalPlants > 0 ? round($totalExpense / $totalPlants, 2) : 0;

        return [
            ['label' => 'Vehicles on Route', 'value' => $vehiclesOnRoute, 'color' => 'blue', 'icon' => 'M3 7h11v10H3z M14 10h4l3 3v4h-7v-7z'],
            ['label' => 'Total KM Today', 'value' => $kmToday, 'color' => 'violet', 'icon' => 'M12 8v4l3 3 M12 22a10 10 0 100-20 10 10 0 000 20z'],
            ['label' => 'Transport Cost Today (₹)', 'value' => $costToday, 'color' => 'amber', 'icon' => 'M12 8v4l3 3 M12 22a10 10 0 100-20 10 10 0 000 20z'],
            ['label' => 'Avg Cost / Plant (₹)', 'value' => $avgCostPerPlant, 'color' => 'emerald', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2 M9 5a3 3 0 006 0'],
            ['label' => 'Monthly Transport Expense (₹)', 'value' => $monthlyExpense, 'color' => 'blue', 'icon' => 'M6 2h9l4 4v16H6z M14 2v5h5 M9 13h6 M9 17h6'],
        ];
    }

    /**
     * Highest-rated Dealer/Farmer, using each record's *current* rating —
     * the manual override when one is active, otherwise the auto score
     * (same effective-score logic as Rating::currentScore()).
     */
    private function topRated(string $rateableClass): ?Rating
    {
        return Rating::where('rateable_type', $rateableClass)
            ->selectRaw('ratings.*, (CASE WHEN is_manual_override THEN manual_score ELSE auto_score END) as effective_score')
            ->where(fn ($q) => $q->whereNotNull('auto_score')->orWhereNotNull('manual_score'))
            ->with('rateable')
            ->orderByDesc('effective_score')
            ->first();
    }

    /**
     * Dispatch lines whose vehicle/crate return is still outstanding (never
     * recorded) or only partially back — crate reconciliation lives per
     * booking/dealer line now, not on the Dispatch header. crate_count/
     * return_status are computed accessors (not DB columns, since crate
     * count itself is derived from qty_per_crate rather than stored) so
     * this is filtered in PHP after a bounded query, not in SQL.
     */
    private function pendingVehicleReturns()
    {
        return DispatchLine::whereHas('dispatch', fn ($q) => $q->whereIn('status', ['dispatched', 'delivered', 'completed']))
            ->whereNotNull('qty_per_crate')
            ->with(['dispatch.vehicle', 'dealer', 'farmer'])
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->filter(fn (DispatchLine $line) => in_array($line->return_status, ['pending', 'partial'], true))
            ->take(10)
            ->values();
    }

    /**
     * Marketing: scoped entirely to dealers assigned to this user (via
     * DealerAssignment), the same scoping BookingController/DispatchController/
     * DealerController/FarmerController already apply to their own lists.
     */
    private function marketing($user)
    {
        $assignedDealerIds = Dealer::whereHas('assignment', fn ($q) => $q->where('marketing_user_id', $user->id))->pluck('id');

        $stats = [
            ['label' => 'Assigned Dealers', 'value' => $assignedDealerIds->count(), 'color' => 'blue', 'icon' => 'M4 7h16v13H4z M8 7V4h8v3 M4 12h16'],
            ['label' => 'Assigned Farmers', 'value' => Farmer::whereIn('dealer_id', $assignedDealerIds)->count(), 'color' => 'emerald', 'icon' => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8z'],
            ['label' => 'My Bookings', 'value' => Booking::whereIn('dealer_id', $assignedDealerIds)->count(), 'color' => 'violet', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2 M9 5a3 3 0 006 0'],
            ['label' => 'Pending Approval', 'value' => Booking::whereIn('dealer_id', $assignedDealerIds)->where('approval_status', 'pending')->count(), 'color' => 'amber', 'icon' => 'M12 8v4l3 3 M12 22a10 10 0 100-20 10 10 0 000 20z'],
        ];

        $recentBookings = Booking::whereIn('dealer_id', $assignedDealerIds)->with(['dealer', 'farmer'])->latest()->limit(5)->get();
        $recentDispatches = Dispatch::whereHas('lines', fn ($q) => $q->whereIn('dealer_id', $assignedDealerIds))->with(['lines.dealer', 'lines.farmer'])->latest()->limit(5)->get();

        return view('dashboard.marketing', compact('stats', 'recentBookings', 'recentDispatches'));
    }

    /**
     * Dealer: scoped entirely to their own dealer_id.
     */
    private function dealer($user)
    {
        $dispatchStatusCounts = Dispatch::whereHas('lines', fn ($q) => $q->where('dealer_id', $user->dealer_id))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            ['label' => 'My Farmers', 'value' => Farmer::where('dealer_id', $user->dealer_id)->count(), 'color' => 'emerald', 'icon' => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8z'],
            ['label' => 'My Bookings', 'value' => Booking::where('dealer_id', $user->dealer_id)->count(), 'color' => 'violet', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2 M9 5a3 3 0 006 0'],
            ['label' => 'Dispatches in progress', 'value' => (int) ($dispatchStatusCounts['loading'] ?? 0) + (int) ($dispatchStatusCounts['dispatched'] ?? 0), 'color' => 'amber', 'icon' => 'M3 7h11v10H3z M14 10h4l3 3v4h-7v-7z'],
            ['label' => 'Dispatches completed', 'value' => (int) ($dispatchStatusCounts['completed'] ?? 0), 'color' => 'blue', 'icon' => 'M5 13l4 4L19 7'],
        ];

        $recentBookings = Booking::where('dealer_id', $user->dealer_id)->with('farmer')->latest()->limit(5)->get();
        $recentDispatches = Dispatch::whereHas('lines', fn ($q) => $q->where('dealer_id', $user->dealer_id))->with(['lines.booking', 'vehicle'])->latest()->limit(5)->get();

        return view('dashboard.dealer', compact('stats', 'recentBookings', 'recentDispatches'));
    }

    /**
     * Accounts: not tenant-scoped (their menu is company-wide Bookings/
     * Payments/Challans/Dispatch View), but strictly non-financial-invoice
     * data — Invoices/Ledger are out of Accounts' menu in this refactor.
     */
    private function accounts()
    {
        $paymentStatusCounts = Booking::selectRaw('payment_status, count(*) as total')->groupBy('payment_status')->pluck('total', 'payment_status');

        $stats = [
            ['label' => 'Total Bookings', 'value' => Booking::count(), 'color' => 'violet', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2 M9 5a3 3 0 006 0'],
            ['label' => 'Payments pending', 'value' => (int) ($paymentStatusCounts['pending'] ?? 0), 'color' => 'amber', 'icon' => 'M12 8v4l3 3 M12 22a10 10 0 100-20 10 10 0 000 20z'],
            ['label' => 'Payments completed', 'value' => (int) ($paymentStatusCounts['completed'] ?? 0), 'color' => 'blue', 'icon' => 'M5 13l4 4L19 7'],
            ['label' => 'Challans issued', 'value' => Dispatch::whereNotNull('challan_no')->count(), 'color' => 'emerald', 'icon' => 'M6 2h9l4 4v16H6z M14 2v5h5 M9 13h6 M9 17h6'],
        ];

        $recentBookings = Booking::with(['dealer', 'farmer'])->latest()->limit(5)->get();
        $recentPayments = Payment::with(['dealer', 'invoice'])->latest()->limit(5)->get();

        return view('dashboard.accounts', compact('stats', 'recentBookings', 'recentPayments'));
    }

    /**
     * Dispatch: not tenant-scoped (dispatch is a company-wide logistics
     * operation), focused on what's actionable — approved bookings waiting
     * to be dispatched, and the live dispatch pipeline by status.
     */
    private function dispatch()
    {
        $dispatchStatusCounts = Dispatch::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $stats = [
            ['label' => 'Approved Bookings', 'value' => Booking::where('approval_status', 'approved')->count(), 'color' => 'violet', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2 M9 5a3 3 0 006 0'],
            ['label' => 'Awaiting loading', 'value' => (int) ($dispatchStatusCounts['pending'] ?? 0), 'color' => 'amber', 'icon' => 'M12 8v4l3 3 M12 22a10 10 0 100-20 10 10 0 000 20z'],
            ['label' => 'Out for delivery', 'value' => (int) ($dispatchStatusCounts['dispatched'] ?? 0), 'color' => 'blue', 'icon' => 'M3 7h11v10H3z M14 10h4l3 3v4h-7v-7z'],
            ['label' => 'Delivered today', 'value' => Dispatch::whereDate('actual_delivery_date', today())->count(), 'color' => 'emerald', 'icon' => 'M5 13l4 4L19 7'],
        ];

        $approvedBookings = Booking::where('approval_status', 'approved')->with(['dealer', 'farmer'])->latest()->limit(5)->get();
        $recentDispatches = Dispatch::with(['lines.dealer', 'lines.farmer', 'vehicle'])->latest()->limit(5)->get();
        $pendingReturns = $this->pendingVehicleReturns();

        $stats = array_merge($stats, $this->transportStats());

        return view('dashboard.dispatch', compact('stats', 'approvedBookings', 'recentDispatches', 'pendingReturns'));
    }
}
