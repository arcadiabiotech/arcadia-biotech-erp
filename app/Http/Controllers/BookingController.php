<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasLocationOptions;
use App\Http\Requests\BookingStoreRequest;
use App\Http\Requests\BookingUpdateRequest;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Farmer;
use App\Services\ApprovalService;
use App\Services\BookingService;
use App\Services\StockReservationService;
use App\Models\Approval;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    use HasLocationOptions;

    public function __construct(
        private readonly BookingService $bookings,
        private readonly StockReservationService $reservations,
        private readonly ApprovalService $approvals,
    ) {
        $this->authorizeResource(Booking::class, 'booking');
    }

    /**
     * Booking list. Search, advanced filters, pagination and role-based
     * scoping all happen here; eager loading avoids N+1 queries on the
     * dealer/farmer/user columns rendered per row.
     */
    public function index(Request $request)
    {
        $bookings = $this->filtered($request)
            ->with(['dealer', 'farmer', 'marketingUser'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('bookings.index', array_merge(
            ['bookings' => $bookings, 'trashed' => $request->boolean('trashed')],
            $this->filterOptions($request->user())
        ));
    }

    public function create(Request $request)
    {
        return view('bookings.form', array_merge(
            ['booking' => new Booking(['booking_date' => now()->toDateString(), 'plant_qty' => 1])],
            $this->formOptions($request->user())
        ));
    }

    public function store(BookingStoreRequest $request)
    {
        // Dispatch Plan's inline "Create Booking" modal posts here via
        // fetch() with Accept: application/json instead of a normal browser
        // form submit — that's also exactly the "spot selling" case: a
        // farmer booked and dispatched the same day, straight out of
        // Dispatch Planning, with no time for the normal multi-day
        // Accounts-verify -> Admin-approve chain. Tag it 'spot' and
        // fast-track it through that chain automatically below instead of
        // leaving it stuck on Draft.
        $isSpotSale = $request->wantsJson();

        $data = $request->validated();
        $data['discount'] = $data['discount'] ?? 0;
        $data['advance_amount'] = $data['advance_amount'] ?? 0;
        $data['booking_no'] = $this->bookings->nextBookingNo();
        $data['approval_status'] = 'draft';
        $data['sale_type'] = $isSpotSale ? 'spot' : 'regular';
        $data['created_by'] = $request->user()->id;
        $data['marketing_user_id'] = Dealer::find($data['dealer_id'])?->assignment?->marketing_user_id;

        if ($request->user()->hasRole('accounts')) {
            $data['accounts_user_id'] = $request->user()->id;
        }

        // Wrapped so an insufficient-stock rejection from reserve() rolls
        // back the booking itself — never leave a booking with no reservation.
        $booking = DB::transaction(function () use ($data, $request, $isSpotSale) {
            $booking = Booking::create($data);
            $this->reservations->reserve($booking);

            if ($isSpotSale) {
                $actor = $request->user();
                $remarks = 'Spot sale — auto-submitted and approved from Dispatch Planning.';

                $this->bookings->submit($booking);
                $this->bookings->verify($booking, $actor, $remarks);
                $this->approvals->record('bookings', $booking->id, Approval::LEVEL_VERIFICATION, 'verified', $actor, $remarks, $booking->booking_no);
                $this->bookings->approve($booking, $actor, $remarks);
                $this->approvals->record('bookings', $booking->id, Approval::LEVEL_APPROVAL, 'approved', $actor, $remarks, $booking->booking_no);
            }

            return $booking;
        });

        ActivityLog::record('bookings', $booking->id, 'create', [], $booking->toArray());

        if ($isSpotSale) {
            $booking->load('farmer:id,farmer_name');

            return response()->json([
                'ok' => true,
                'booking' => [
                    'id' => $booking->id,
                    'booking_no' => $booking->booking_no,
                    'dealer_id' => $booking->dealer_id,
                    'farmer_id' => $booking->farmer_id,
                    'farmer_name' => $booking->farmer?->farmer_name,
                    'variety' => $booking->variety,
                    'sale_type' => $booking->sale_type,
                    'booked_qty' => $booking->plant_qty,
                    'dispatched_qty' => 0,
                    'balance_qty' => $booking->plant_qty,
                ],
            ]);
        }

        return redirect()->route('bookings.show', $booking)->with('success', 'Booking created successfully.');
    }

    /**
     * Booking profile page: details, timeline, audit history, and a
     * placeholder for the future documents module.
     */
    public function show(Booking $booking)
    {
        $booking->load(['dealer.assignment.marketingUser', 'farmer', 'marketingUser', 'accountsUser', 'createdBy', 'updatedBy', 'approvedBy']);

        $activity = ActivityLog::where('module', 'bookings')->where('record_id', $booking->id)->latest()->limit(30)->get();

        $reservation = $booking->reservation;
        $reservationActivity = $reservation
            ? ActivityLog::where('module', 'stock_reservations')->where('record_id', $reservation->id)->latest()->limit(30)->get()
            : collect();

        $approvalLevels = $this->approvals->levelsFor('bookings', $booking->id)->load(['approvedBy', 'rejectedBy', 'holdBy', 'unlockBy']);

        return view('bookings.show', compact('booking', 'activity', 'reservation', 'reservationActivity', 'approvalLevels'));
    }

    public function edit(Booking $booking, Request $request)
    {
        return view('bookings.form', array_merge(
            ['booking' => $booking],
            $this->formOptions($request->user(), $booking)
        ));
    }

    public function update(BookingUpdateRequest $request, Booking $booking)
    {
        $original = $booking->toArray();

        $data = $request->validated();
        $data['discount'] = $data['discount'] ?? 0;
        $data['advance_amount'] = $data['advance_amount'] ?? 0;
        $data['updated_by'] = $request->user()->id;

        DB::transaction(function () use ($booking, $data) {
            $booking->update($data);
            $this->reservations->updateReservation($booking);
        });

        ActivityLog::record('bookings', $booking->id, 'update', $original, $booking->fresh()->toArray());

        return redirect()->route('bookings.show', $booking)->with('success', 'Booking updated successfully.');
    }

    /**
     * "Booking Cancelled" from the Stock Reservation engine's perspective —
     * releases the reservation before soft-deleting the booking.
     */
    public function destroy(Booking $booking)
    {
        DB::transaction(function () use ($booking) {
            $this->reservations->release($booking, 'cancelled', auth()->id());
            $booking->update(['deleted_by' => auth()->id()]);
            $booking->delete();
        });

        ActivityLog::record('bookings', $booking->id, 'delete');

        return redirect()->route('bookings.index')->with('success', 'Booking deleted successfully.');
    }

    public function restore(Booking $booking)
    {
        $this->authorize('restore', $booking);

        DB::transaction(function () use ($booking) {
            $booking->restore();
            $booking->update(['deleted_by' => null]);
        });

        ActivityLog::record('bookings', $booking->id, 'update', [], [], 'Booking restored');

        return redirect()->route('bookings.index')->with('success', 'Booking restored successfully.');
    }

    public function submit(Booking $booking)
    {
        $this->authorize('submit', $booking);

        $this->bookings->submit($booking);

        return back()->with('success', 'Booking submitted for approval.');
    }

    /**
     * Accounts Verification — level 1 of the Approval Engine. Gates whether
     * the booking can ever reach Admin's final approve().
     */
    public function verify(Request $request, Booking $booking)
    {
        $this->authorize('verify', $booking);

        $remarks = $request->string('remarks')->toString() ?: null;

        DB::transaction(function () use ($request, $booking, $remarks) {
            $this->bookings->verify($booking, $request->user(), $remarks);
            $this->approvals->record('bookings', $booking->id, Approval::LEVEL_VERIFICATION, 'verified', $request->user(), $remarks, $booking->booking_no, $this->notifyRecipients($booking));
        });

        return back()->with('success', 'Booking verified — ready for Admin approval.');
    }

    public function approve(Request $request, Booking $booking)
    {
        $this->authorize('approve', $booking);

        $remarks = $request->string('remarks')->toString() ?: null;

        DB::transaction(function () use ($request, $booking, $remarks) {
            $this->bookings->approve($booking, $request->user(), $remarks);
            $this->approvals->record('bookings', $booking->id, Approval::LEVEL_APPROVAL, 'approved', $request->user(), $remarks, $booking->booking_no, $this->notifyRecipients($booking));
        });

        return back()->with('success', 'Booking approved.');
    }

    public function reject(Request $request, Booking $booking)
    {
        $this->authorize('reject', $booking);

        $remarks = $request->string('remarks')->toString() ?: null;
        $level = $booking->approval_status === 'verified' ? Approval::LEVEL_APPROVAL : Approval::LEVEL_VERIFICATION;

        DB::transaction(function () use ($request, $booking, $remarks, $level) {
            $this->bookings->reject($booking, $request->user(), $remarks);
            $this->reservations->release($booking, 'released', $request->user()->id);
            $this->approvals->record('bookings', $booking->id, $level, 'rejected', $request->user(), $remarks, $booking->booking_no, $this->notifyRecipients($booking));
        });

        return back()->with('success', 'Booking rejected.');
    }

    public function hold(Request $request, Booking $booking)
    {
        $this->authorize('hold', $booking);

        $remarks = $request->string('remarks')->toString() ?: null;
        $level = $booking->approval_status === 'approved' ? Approval::LEVEL_APPROVAL : Approval::LEVEL_VERIFICATION;

        DB::transaction(function () use ($request, $booking, $remarks, $level) {
            $this->bookings->hold($booking, $remarks);
            $this->approvals->record('bookings', $booking->id, $level, 'hold', $request->user(), $remarks, $booking->booking_no, $this->notifyRecipients($booking));
        });

        return back()->with('success', 'Booking put on hold.');
    }

    public function unlock(Request $request, Booking $booking)
    {
        $this->authorize('unlock', $booking);

        $remarks = $request->string('remarks')->toString() ?: null;

        DB::transaction(function () use ($request, $booking, $remarks) {
            $this->bookings->unlock($booking, $remarks);
            $this->approvals->record('bookings', $booking->id, Approval::LEVEL_APPROVAL, 'unlocked', $request->user(), $remarks, $booking->booking_no, $this->notifyRecipients($booking));
        });

        return back()->with('success', 'Booking unlocked for editing.');
    }

    /**
     * Marks the booking's dispatch as Completed and converts its stock
     * reservation into a real stock deduction. No Dispatch module exists
     * yet, so this is a stand-in trigger on Booking's existing
     * dispatch_status column — once a real Dispatch module is built, it
     * will call StockReservationService::convert() the same way.
     */
    public function completeDispatch(Request $request, Booking $booking)
    {
        $this->authorize('updateDispatchStatus', $booking);

        DB::transaction(function () use ($request, $booking) {
            $this->reservations->convert($booking, actorId: $request->user()->id);
            $booking->update(['dispatch_status' => 'completed', 'updated_by' => $request->user()->id]);
            $this->approvals->record('bookings', $booking->id, Approval::LEVEL_APPROVAL, 'completed', $request->user(), null, $booking->booking_no, $this->notifyRecipients($booking));
        });

        return back()->with('success', 'Dispatch marked complete — reservation converted to a stock issue.');
    }

    /**
     * Who cares about this booking's approval decisions: whoever created
     * it, and the Marketing user managing its dealer (if any) — excluding
     * the actor themselves, who doesn't need to be told about their own action.
     */
    private function notifyRecipients(Booking $booking)
    {
        return collect([$booking->createdBy, $booking->marketingUser])
            ->filter()
            ->unique('id')
            ->reject(fn ($user) => $user->id === auth()->id());
    }

    public function receivePayment(Request $request, Booking $booking)
    {
        $this->authorize('receivePayment', $booking);

        $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);

        $this->bookings->receivePayment($booking, $request->user(), (float) $request->input('amount'));

        return back()->with('success', 'Payment recorded.');
    }

    public function print(Booking $booking)
    {
        $this->authorize('view', $booking);

        $booking->load(['dealer', 'farmer']);

        return view('bookings.print', compact('booking'));
    }

    public function pdf(Booking $booking)
    {
        $this->authorize('view', $booking);

        $booking->load(['dealer', 'farmer']);

        return Pdf::loadView('bookings.print', compact('booking'))
            ->download("{$booking->booking_no}.pdf");
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Booking::class);

        $bookings = $this->filtered($request)->with(['dealer', 'farmer'])->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bookings.csv"',
        ];

        return response()->streamDownload(function () use ($bookings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Booking No', 'Dealer', 'Farmer', 'Variety', 'Booking Date', 'Qty', 'Rate', 'Amount', 'Discount', 'Advance', 'Balance', 'Payment Status', 'Approval Status', 'Dispatch Status', 'Invoice Status']);
            foreach ($bookings as $booking) {
                fputcsv($out, [
                    $booking->booking_no,
                    $booking->dealer?->dealer_name,
                    $booking->farmer?->farmer_name,
                    $booking->variety,
                    $booking->booking_date?->format('Y-m-d'),
                    $booking->plant_qty,
                    $booking->plant_rate,
                    $booking->booking_amount,
                    $booking->discount,
                    $booking->advance_amount,
                    $booking->balance_amount,
                    $booking->payment_status,
                    $booking->approval_status,
                    $booking->dispatch_status,
                    $booking->invoice_status,
                ]);
            }
            fclose($out);
        }, 'bookings.csv', $headers);
    }

    /**
     * Shared search/filter/role-scope query builder, reused by index() and export().
     */
    private function filtered(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();

        return Booking::query()
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('booking_no', 'like', "%{$search}%")
                ->orWhereHas('farmer', fn ($f) => $f->where('farmer_name', 'like', "%{$search}%"))
                ->orWhereHas('dealer', fn ($d) => $d->where('dealer_name', 'like', "%{$search}%"))))
            ->when($request->filled('dealer'), fn ($q) => $q->where('dealer_id', $request->input('dealer')))
            ->when($request->filled('farmer'), fn ($q) => $q->where('farmer_id', $request->input('farmer')))
            ->when($request->filled('variety'), fn ($q) => $q->where('variety', $request->input('variety')))
            ->when($request->filled('approval_status'), fn ($q) => $q->where('approval_status', $request->input('approval_status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->input('payment_status')))
            ->when($request->filled('dispatch_status'), fn ($q) => $q->where('dispatch_status', $request->input('dispatch_status')))
            ->when($request->filled('invoice_status'), fn ($q) => $q->where('invoice_status', $request->input('invoice_status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('booking_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('booking_date', '<=', $request->input('date_to')))
            ->when($user->hasRole('marketing'), fn ($q) => $q->whereHas('dealer.assignment', fn ($qq) => $qq->where('marketing_user_id', $user->id)))
            ->when($user->hasRole('dealer'), fn ($q) => $q->where('dealer_id', $user->dealer_id))
            // Role-based access refactor: Dispatch's menu item is "Approved
            // Bookings" specifically — they only ever need bookings that
            // are actually eligible to dispatch, not the full pipeline
            // (draft/pending/verified/rejected/hold).
            ->when($user->hasRole('dispatch'), fn ($q) => $q->where('approval_status', 'approved'));
    }

    private function filterOptions($user): array
    {
        return [
            'dealers' => $this->visibleDealers($user),
            'varieties' => Booking::VARIETIES,
            'approvalStatuses' => Booking::APPROVAL_STATUSES,
            'paymentStatuses' => Booking::PAYMENT_STATUSES,
            'dispatchStatuses' => Booking::DISPATCH_STATUSES,
            'invoiceStatuses' => Booking::INVOICE_STATUSES,
        ];
    }

    private function formOptions($user, ?Booking $booking = null): array
    {
        $dealers = $this->visibleDealers($user);

        if ($booking && ! $dealers->contains('id', $booking->dealer_id)) {
            $dealers = $dealers->push(Dealer::find($booking->dealer_id))->filter()->values();
        }

        $farmers = Farmer::whereIn('dealer_id', $dealers->pluck('id'))->orderBy('farmer_name')->get(['id', 'farmer_name', 'dealer_id']);

        return array_merge(
            [
                'dealers' => $dealers,
                'farmers' => $farmers,
                'varieties' => Booking::VARIETIES,
                'canChangePricing' => $user->hasRole(['super-admin', 'admin']),
            ],
            // Feeds the "register new dealer/farmer" quick-add modals' location cascade.
            $this->locationOptions()
        );
    }

    private function visibleDealers($user)
    {
        return Dealer::when($user->hasRole('marketing'), fn ($q) => $q->whereHas('assignment', fn ($qq) => $qq->where('marketing_user_id', $user->id)))
            ->orderBy('dealer_name')
            ->get(['id', 'dealer_name']);
    }
}
