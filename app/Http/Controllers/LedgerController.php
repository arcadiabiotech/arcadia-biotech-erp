<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\Farmer;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;

/**
 * Read-only reporting surface over ledger_entries — Outstanding Report,
 * Dealer Ledger and Farmer Ledger statements. There is no CRUD lifecycle
 * here (entries are only ever written by InvoiceService/PaymentService), so
 * this controller uses simple role checks rather than a Policy class, the
 * same way VarietyStockController does for its report view.
 *
 * Role-based access refactor: Ledger is now Admin/Super Admin only —
 * neither Accounts' nor Dealer's current menu includes it (Ledger was
 * previously visible to both). No permission fallback on purpose: a
 * lingering ledger.view grant on those roles must not reopen this.
 */
class LedgerController extends Controller
{
    public function outstanding(Request $request)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin']), 403);

        $search = $request->string('search')->toString();

        $dealers = Dealer::query()
            ->when($search, fn ($q) => $q->where('dealer_name', 'like', "%{$search}%")->orWhere('dealer_code', 'like', "%{$search}%"))
            ->orderBy('dealer_name')
            ->paginate(20)
            ->withQueryString();

        $dealers->getCollection()->transform(function (Dealer $dealer) {
            $dealer->ledger_balance = (float) (LedgerEntry::where('dealer_id', $dealer->id)->latest('id')->value('balance') ?? 0);

            return $dealer;
        });

        return view('ledger.outstanding', compact('dealers'));
    }

    public function exportOutstanding(Request $request)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin']), 403);

        $dealers = Dealer::orderBy('dealer_name')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="outstanding-report.csv"',
        ];

        return response()->streamDownload(function () use ($dealers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Dealer Code', 'Dealer Name', 'Outstanding Balance']);
            foreach ($dealers as $dealer) {
                $balance = (float) (LedgerEntry::where('dealer_id', $dealer->id)->latest('id')->value('balance') ?? 0);
                fputcsv($out, [$dealer->dealer_code, $dealer->dealer_name, $balance]);
            }
            fclose($out);
        }, 'outstanding-report.csv', $headers);
    }

    public function dealer(Request $request, Dealer $dealer)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin']), 403);

        $entries = LedgerEntry::where('dealer_id', $dealer->id)
            ->with('farmer')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $balance = (float) (LedgerEntry::where('dealer_id', $dealer->id)->latest('id')->value('balance') ?? 0);

        return view('ledger.dealer', compact('dealer', 'entries', 'balance'));
    }

    public function exportDealer(Request $request, Dealer $dealer)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin']), 403);

        $entries = LedgerEntry::where('dealer_id', $dealer->id)->orderBy('entry_date')->orderBy('id')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="dealer-'.$dealer->dealer_code.'-statement.csv"',
        ];

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Module', 'Debit', 'Credit', 'Balance', 'Remarks']);
            foreach ($entries as $entry) {
                fputcsv($out, [$entry->entry_date?->format('Y-m-d'), $entry->module, $entry->debit, $entry->credit, $entry->balance, $entry->remarks]);
            }
            fclose($out);
        }, 'dealer-statement.csv', $headers);
    }

    /**
     * Farmer statement — farmers have no running-balance column of their
     * own (only the dealer does), so this view sums debit/credit for the
     * farmer-scoped rows instead of relying on the stored balance column.
     */
    public function farmer(Request $request, Farmer $farmer)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin']), 403);

        $entries = LedgerEntry::where('farmer_id', $farmer->id)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $balance = (float) LedgerEntry::where('farmer_id', $farmer->id)
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as net')
            ->value('net');

        return view('ledger.farmer', compact('farmer', 'entries', 'balance'));
    }

    public function exportFarmer(Request $request, Farmer $farmer)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin']), 403);

        $entries = LedgerEntry::where('farmer_id', $farmer->id)->orderBy('entry_date')->orderBy('id')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="farmer-'.$farmer->farmer_code.'-statement.csv"',
        ];

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Module', 'Debit', 'Credit', 'Remarks']);
            foreach ($entries as $entry) {
                fputcsv($out, [$entry->entry_date?->format('Y-m-d'), $entry->module, $entry->debit, $entry->credit, $entry->remarks]);
            }
            fclose($out);
        }, 'farmer-statement.csv', $headers);
    }
}
