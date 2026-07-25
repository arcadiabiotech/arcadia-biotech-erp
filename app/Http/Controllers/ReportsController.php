<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Reports hub — a single landing page linking out to every export/statement
 * already built across the app (Bookings, Dispatch, Invoices, Payments,
 * Ledger). Each of those modules owns its own CSV export and filtering;
 * this controller intentionally does not duplicate that logic, it just
 * indexes it in one place for Admin.
 *
 * Role-based access refactor: Reports is now Admin/Super Admin only.
 * Marketing must NEVER see Reports (explicit requirement), and Reports is
 * not in Accounts' current menu either — both were previously admitted.
 */
class ReportsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin']), 403);

        $reports = collect([
            [
                'title' => 'Bookings',
                'description' => 'All bookings with approval, dispatch and payment status.',
                'route' => 'bookings.export',
            ],
            [
                'title' => 'Dispatches',
                'description' => 'Shipment log with vehicle, driver and delivery status.',
                'route' => 'dispatches.export',
            ],
            [
                'title' => 'Invoices',
                'description' => 'Invoice register with subtotal, discount, tax and balance.',
                'route' => 'invoices.export',
            ],
            [
                'title' => 'Payments',
                'description' => 'Every payment received, by mode and reference.',
                'route' => 'payments.export',
            ],
            [
                'title' => 'Outstanding (Ledger)',
                'description' => 'Dealer-wise running balance across invoices and payments.',
                'route' => 'ledger.export',
            ],
            [
                'title' => 'Laboratory Operations',
                'description' => 'Daily checklists, maintenance, media, chemical and contamination logs with compliance %.',
                'route' => 'lab-reports.index',
            ],
            [
                'title' => 'Crate Return',
                'description' => 'Crates sent vs. returned vs. damaged, per dispatch — with pending/returned status.',
                'route' => 'reports.crate-returns',
            ],
            [
                'title' => 'Transport Cost',
                'description' => 'Vehicle-wise, dealer-wise and monthly transport cost — odometer KM, fuel/driver/toll cost per dispatch.',
                'route' => 'reports.transport-cost',
            ],
        ]);

        return view('reports.index', compact('reports'));
    }
}
