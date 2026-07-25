<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceStoreRequest;
use App\Http\Requests\InvoiceUpdateRequest;
use App\Models\ActivityLog;
use App\Models\Dealer;
use App\Models\Dispatch;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
    ) {
        $this->authorizeResource(Invoice::class, 'invoice');
    }

    /**
     * Invoice list. Search, advanced filters, pagination and role-based
     * scoping all happen here; eager loading avoids N+1 queries on the
     * dispatch/dealer/lines columns rendered per row.
     */
    public function index(Request $request)
    {
        $invoices = $this->filtered($request)
            ->with(['dispatch', 'dealer', 'lines'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('invoices.index', array_merge(
            ['invoices' => $invoices, 'trashed' => $request->boolean('trashed')],
            $this->filterOptions($request->user())
        ));
    }

    public function create(Request $request)
    {
        return view('invoices.form', [
            'invoice' => new Invoice(['invoice_date' => now()->toDateString()]),
            'dealerGroups' => $this->eligibleDealerGroups(),
        ]);
    }

    public function store(InvoiceStoreRequest $request)
    {
        $data = $request->validated();

        $dispatch = Dispatch::with('lines.booking')->findOrFail($data['dispatch_id']);
        $dealerLines = $dispatch->lines->where('dealer_id', (int) $data['dealer_id']);

        abort_if($dealerLines->isEmpty(), 404);

        $invoice = DB::transaction(function () use ($data, $dispatch, $dealerLines, $request) {
            $invoice = Invoice::create([
                'invoice_no' => $this->invoices->nextInvoiceNo(),
                'dispatch_id' => $dispatch->id,
                'dealer_id' => $data['dealer_id'],
                'invoice_date' => $data['invoice_date'],
                'subtotal' => round((float) $dealerLines->sum('amount'), 2),
                'discount' => $data['discount'] ?? 0,
                'tax' => $data['tax'] ?? 0,
                'paid_amount' => 0,
                'status' => 'draft',
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($dealerLines as $line) {
                InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'dispatch_line_id' => $line->id,
                    'booking_id' => $line->booking_id,
                    'farmer_id' => $line->farmer_id,
                    'qty' => $line->billable_qty,
                    'rate' => $line->booking->plant_rate,
                    'amount' => $line->amount,
                ]);
            }

            return $invoice;
        });

        ActivityLog::record('invoices', $invoice->id, 'create', [], $invoice->fresh('lines')->toArray());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created as draft.');
    }

    /**
     * Invoice profile page: line items, payments, timeline and audit history.
     */
    public function show(Invoice $invoice)
    {
        $invoice->load(['dispatch', 'dealer', 'lines.booking', 'lines.farmer', 'payments', 'createdBy', 'updatedBy']);

        $activity = ActivityLog::where('module', 'invoices')->where('record_id', $invoice->id)->latest()->limit(30)->get();

        return view('invoices.show', compact('invoice', 'activity'));
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load(['dispatch', 'dealer', 'lines']);

        return view('invoices.form', ['invoice' => $invoice]);
    }

    public function update(InvoiceUpdateRequest $request, Invoice $invoice)
    {
        $original = $invoice->toArray();

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $invoice->update($data);

        ActivityLog::record('invoices', $invoice->id, 'update', $original, $invoice->fresh()->toArray());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            $invoice->update(['deleted_by' => auth()->id()]);
            $invoice->delete();
        });

        ActivityLog::record('invoices', $invoice->id, 'delete');

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted successfully.');
    }

    public function restore(Invoice $invoice)
    {
        $this->authorize('restore', $invoice);

        DB::transaction(function () use ($invoice) {
            $invoice->restore();
            $invoice->update(['deleted_by' => null]);
        });

        ActivityLog::record('invoices', $invoice->id, 'update', [], [], 'Invoice restored');

        return redirect()->route('invoices.index')->with('success', 'Invoice restored successfully.');
    }

    /**
     * Accounts: "Generate Invoice" -> Ledger Debit, in one transaction.
     */
    public function generate(Request $request, Invoice $invoice)
    {
        $this->authorize('generate', $invoice);

        DB::transaction(function () use ($request, $invoice) {
            $this->invoices->generate($invoice, $request->user()->id);
        });

        return back()->with('success', 'Invoice generated — ledger debited.');
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $this->authorize('cancel', $invoice);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($validated, $invoice, $request) {
            $this->invoices->cancel($invoice, $validated['reason'], $request->user()->id);
        });

        return back()->with('success', 'Invoice cancelled.');
    }

    public function unlock(Request $request, Invoice $invoice)
    {
        $this->authorize('unlock', $invoice);

        $this->invoices->unlock($invoice, $request->user()->id);

        return back()->with('success', 'Invoice unlocked for editing.');
    }

    public function print(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load(['dispatch', 'dealer', 'lines.booking', 'lines.farmer', 'payments']);

        return view('invoices.print', compact('invoice'));
    }

    public function pdf(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load(['dispatch', 'dealer', 'lines.booking', 'lines.farmer', 'payments']);

        return Pdf::loadView('invoices.print', compact('invoice'))
            ->download("{$invoice->invoice_no}.pdf");
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $this->filtered($request)->with(['dispatch', 'dealer', 'lines'])->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="invoices.csv"',
        ];

        return response()->streamDownload(function () use ($invoices) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice No', 'Dispatch No', 'Dealer', 'Bookings', 'Invoice Date', 'Subtotal', 'Discount', 'Tax', 'Grand Total', 'Paid', 'Balance', 'Status']);
            foreach ($invoices as $invoice) {
                fputcsv($out, [
                    $invoice->invoice_no,
                    $invoice->dispatch?->dispatch_no,
                    $invoice->dealer?->dealer_name,
                    $invoice->lines->count(),
                    $invoice->invoice_date?->format('Y-m-d'),
                    $invoice->subtotal,
                    $invoice->discount,
                    $invoice->tax,
                    $invoice->grand_total,
                    $invoice->paid_amount,
                    $invoice->balance_amount,
                    $invoice->status,
                ]);
            }
            fclose($out);
        }, 'invoices.csv', $headers);
    }

    /**
     * Shared search/filter/role-scope query builder, reused by index() and export().
     */
    private function filtered(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();

        return Invoice::query()
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('invoice_no', 'like', "%{$search}%")
                ->orWhereHas('dispatch', fn ($d) => $d->where('dispatch_no', 'like', "%{$search}%"))
                ->orWhereHas('dealer', fn ($d) => $d->where('dealer_name', 'like', "%{$search}%"))
                ->orWhereHas('lines.booking', fn ($b) => $b->where('booking_no', 'like', "%{$search}%"))))
            ->when($request->filled('dealer'), fn ($q) => $q->where('dealer_id', $request->input('dealer')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('invoice_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('invoice_date', '<=', $request->input('date_to')))
            ->when($user->hasRole('dealer'), fn ($q) => $q->where('dealer_id', $user->dealer_id));
    }

    private function filterOptions($user): array
    {
        return [
            'dealers' => Dealer::orderBy('dealer_name')->get(['id', 'dealer_name']),
            'statuses' => Invoice::STATUSES,
        ];
    }

    /**
     * (Dispatch, dealer) groups eligible for a new invoice: the dispatch is
     * Completed and this dealer's lines on it don't already have an invoice
     * — kept in sync with InvoiceStoreRequest::allowedPairs(), which is the
     * same scoping enforced at validation time. Invoices are Admin-only
     * (InvoicePolicy), so unlike Dispatch there's no role-based scoping here.
     */
    private function eligibleDealerGroups()
    {
        $invoicedPairs = Invoice::withTrashed()->get(['dispatch_id', 'dealer_id'])
            ->map(fn ($invoice) => $invoice->dispatch_id.':'.$invoice->dealer_id);

        return Dispatch::where('status', 'completed')
            ->with(['lines.dealer', 'lines.booking', 'lines.farmer'])
            ->orderBy('dispatch_no')
            ->get()
            ->flatMap(function (Dispatch $dispatch) use ($invoicedPairs) {
                return $dispatch->lines->groupBy('dealer_id')
                    ->reject(fn ($lines, $dealerId) => $invoicedPairs->contains("{$dispatch->id}:{$dealerId}"))
                    ->map(fn ($lines) => ['dispatch' => $dispatch, 'dealer' => $lines->first()->dealer, 'lines' => $lines]);
            })
            ->values();
    }
}
