<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentStoreRequest;
use App\Models\Dealer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {
        $this->authorizeResource(Payment::class, 'payment');
    }

    /**
     * Payment list. Search, filters, pagination and role-based scoping all
     * happen here; eager loading avoids N+1 queries on the invoice/dealer/
     * farmer columns rendered per row.
     */
    public function index(Request $request)
    {
        $payments = $this->filtered($request)
            ->with(['invoice', 'dealer', 'farmer'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('payments.index', array_merge(
            ['payments' => $payments],
            $this->filterOptions()
        ));
    }

    public function create(Request $request)
    {
        return view('payments.form', [
            'payment' => new Payment(['payment_date' => now()->toDateString()]),
            'invoices' => $this->eligibleInvoices($request->user()),
            'paymentModes' => Payment::PAYMENT_MODES,
        ]);
    }

    public function store(PaymentStoreRequest $request)
    {
        $data = $request->validated();

        $invoice = Invoice::findOrFail($data['invoice_id']);

        $payment = DB::transaction(fn () => $this->payments->receive($invoice, $data, $request->user()));

        return redirect()->route('payments.show', $payment)->with('success', 'Payment recorded successfully.');
    }

    public function show(Payment $payment)
    {
        $payment->load(['invoice', 'dealer', 'farmer', 'createdBy']);

        return view('payments.show', compact('payment'));
    }

    public function receipt(Payment $payment)
    {
        $this->authorize('view', $payment);

        $payment->load(['invoice', 'dealer', 'farmer']);

        return view('payments.receipt', compact('payment'));
    }

    public function receiptPdf(Payment $payment)
    {
        $this->authorize('view', $payment);

        $payment->load(['invoice', 'dealer', 'farmer']);

        return Pdf::loadView('payments.receipt', compact('payment'))
            ->download("{$payment->payment_no}.pdf");
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        $payments = $this->filtered($request)->with(['invoice', 'dealer', 'farmer'])->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="payments.csv"',
        ];

        return response()->streamDownload(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Payment No', 'Invoice No', 'Dealer', 'Farmer', 'Payment Date', 'Mode', 'Reference No', 'Bank', 'Amount']);
            foreach ($payments as $payment) {
                fputcsv($out, [
                    $payment->payment_no,
                    $payment->invoice?->invoice_no,
                    $payment->dealer?->dealer_name,
                    $payment->farmer?->farmer_name,
                    $payment->payment_date?->format('Y-m-d'),
                    $payment->payment_mode,
                    $payment->reference_no,
                    $payment->bank_name,
                    $payment->amount,
                ]);
            }
            fclose($out);
        }, 'payments.csv', $headers);
    }

    /**
     * Shared search/filter/role-scope query builder, reused by index() and export().
     */
    private function filtered(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();

        return Payment::query()
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('payment_no', 'like', "%{$search}%")
                ->orWhere('reference_no', 'like', "%{$search}%")
                ->orWhereHas('invoice', fn ($i) => $i->where('invoice_no', 'like', "%{$search}%"))
                ->orWhereHas('dealer', fn ($d) => $d->where('dealer_name', 'like', "%{$search}%"))
                ->orWhereHas('farmer', fn ($f) => $f->where('farmer_name', 'like', "%{$search}%"))))
            ->when($request->filled('dealer'), fn ($q) => $q->where('dealer_id', $request->input('dealer')))
            ->when($request->filled('payment_mode'), fn ($q) => $q->where('payment_mode', $request->input('payment_mode')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('payment_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('payment_date', '<=', $request->input('date_to')))
            ->when($user->hasRole('dealer'), fn ($q) => $q->where('dealer_id', $user->dealer_id));
    }

    private function filterOptions(): array
    {
        return [
            'dealers' => Dealer::orderBy('dealer_name')->get(['id', 'dealer_name']),
            'paymentModes' => Payment::PAYMENT_MODES,
        ];
    }

    /**
     * Invoices eligible to receive a payment — kept in sync with
     * PaymentStoreRequest::allowedInvoiceIds(), which is the source of
     * truth enforced at validation time; this is just the same scoping
     * used to populate the create-form dropdown.
     */
    private function eligibleInvoices($user)
    {
        return Invoice::query()
            ->whereIn('status', ['generated', 'partially_paid'])
            ->when($user->hasRole('dealer'), fn ($q) => $q->where('dealer_id', $user->dealer_id))
            ->with(['dealer', 'farmer'])
            ->orderBy('invoice_no')
            ->get();
    }
}
