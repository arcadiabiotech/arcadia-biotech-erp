<?php

namespace App\Http\Controllers;

use App\Http\Requests\DealerStoreRequest;
use App\Http\Requests\DealerUpdateRequest;
use App\Models\ActivityLog;
use App\Models\Dealer;
use App\Models\DealerAssignment;
use App\Models\District;
use App\Models\RatingHistory;
use App\Models\State;
use App\Models\Taluka;
use App\Models\Village;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealerController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
    ) {
        $this->authorizeResource(Dealer::class, 'dealer');
    }

    /**
     * Dealer list. Search, filter, pagination and role-based scoping all
     * happen here; eager loading avoids N+1 queries on the location and
     * assignment columns rendered per row.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $statusFilter = $request->string('status')->toString();
        $stateFilter = $request->string('state')->toString();
        $trashed = $request->boolean('trashed');

        $dealers = Dealer::with(['state', 'district', 'assignment.marketingUser'])
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('dealer_code', 'like', "%{$search}%")
                ->orWhere('dealer_name', 'like', "%{$search}%")
                ->orWhere('firm_name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")))
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter === '1'))
            ->when($stateFilter, fn ($query) => $query->where('state_id', $stateFilter))
            ->when($user->hasRole('marketing'), fn ($query) => $query->whereHas('assignment', fn ($q) => $q->where('marketing_user_id', $user->id)))
            ->when($user->hasRole('dealer'), fn ($query) => $query->where('id', $user->dealer_id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dealers.index', [
            'dealers' => $dealers,
            'states' => State::where('status', true)->orderBy('name')->get(),
            'trashed' => $trashed,
        ]);
    }

    public function create()
    {
        return view('dealers.form', array_merge(
            ['dealer' => new Dealer(['status' => true, 'credit_limit' => 0])],
            $this->locationOptions()
        ));
    }

    public function store(DealerStoreRequest $request)
    {
        $data = $this->prepared($request);

        // Registration rule: a Dealer cannot be created until its mobile
        // number has been OTP-verified. This is the authoritative,
        // server-side gate — the OTP UI on the form is a courtesy only.
        if (! $this->otp->isVerified($data['mobile'], 'registration')) {
            throw ValidationException::withMessages([
                'mobile' => 'Please verify this mobile number via OTP before registering the dealer.',
            ]);
        }

        $data['dealer_code'] = $this->nextDealerCode();
        $data['created_by'] = $request->user()->id;

        $dealer = DB::transaction(function () use ($data, $request) {
            $dealer = Dealer::create($data);

            // User hierarchy: a Dealer created by a Marketing user belongs
            // to that Marketing user automatically — the same
            // DealerAssignment mechanism Admin uses via the Dealer
            // Assignments screen, just triggered by the creator instead of
            // requiring a separate admin step.
            if ($request->user()->hasRole('marketing')) {
                DealerAssignment::create([
                    'dealer_id' => $dealer->id,
                    'marketing_user_id' => $request->user()->id,
                    'assigned_by' => $request->user()->id,
                    'assigned_date' => now()->toDateString(),
                    'status' => true,
                ]);
            }

            return $dealer;
        });

        $this->otp->consume($data['mobile'], 'registration');

        ActivityLog::record('dealers', $dealer->id, 'create', [], $dealer->toArray());

        // Dispatch plan's and booking form's inline "register new dealer"
        // modals post here via fetch() with Accept: application/json
        // instead of a normal browser form submit — give it back the
        // fields it needs to select the new dealer without a page reload,
        // rather than the usual redirect (mirrors FarmerController::store()).
        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'dealer' => $dealer->only(['id', 'dealer_name', 'firm_name', 'dealer_code']),
            ]);
        }

        return redirect()->route('dealers.index')->with('success', 'Dealer registered successfully.');
    }

    /**
     * Dealer profile page: details, location, audit history, and
     * placeholders for the future ledger and documents modules.
     */
    public function show(Dealer $dealer)
    {
        $dealer->load(['state', 'district', 'taluka', 'village', 'assignment.marketingUser', 'createdBy', 'updatedBy', 'ratingRecord']);

        $activity = ActivityLog::where('module', 'dealers')->where('record_id', $dealer->id)->latest()->limit(20)->get();
        $ratingHistory = RatingHistory::where('module', 'dealer')->where('rateable_id', $dealer->id)->latest('created_at')->get();

        return view('dealers.show', compact('dealer', 'activity', 'ratingHistory'));
    }

    public function edit(Dealer $dealer)
    {
        return view('dealers.form', array_merge(
            ['dealer' => $dealer],
            $this->locationOptions()
        ));
    }

    public function update(DealerUpdateRequest $request, Dealer $dealer)
    {
        $original = $dealer->toArray();

        $data = $this->prepared($request);
        $data['updated_by'] = $request->user()->id;

        $dealer->update($data);

        ActivityLog::record('dealers', $dealer->id, 'update', $original, $dealer->fresh()->toArray());

        return redirect()->route('dealers.index')->with('success', 'Dealer updated successfully.');
    }

    public function destroy(Dealer $dealer)
    {
        DB::transaction(function () use ($dealer) {
            $dealer->update(['deleted_by' => auth()->id()]);
            $dealer->delete();
        });

        ActivityLog::record('dealers', $dealer->id, 'delete');

        return redirect()->route('dealers.index')->with('success', 'Dealer deleted successfully.');
    }

    /**
     * Restore a soft-deleted dealer. Not part of the standard resourceful
     * methods, so authorization is checked explicitly. The route is bound
     * withTrashed() so the model resolves despite the SoftDeletingScope.
     */
    public function restore(Dealer $dealer)
    {
        $this->authorize('restore', $dealer);

        DB::transaction(function () use ($dealer) {
            $dealer->restore();
            $dealer->update(['deleted_by' => null]);
        });

        ActivityLog::record('dealers', $dealer->id, 'update', [], [], 'Dealer restored');

        return redirect()->route('dealers.index')->with('success', 'Dealer restored successfully.');
    }

    /**
     * Shared field prep for store/update: title-case the name fields and
     * make sure credit_limit never lands on the not-null column as NULL
     * when the field is left blank.
     */
    private function prepared(DealerStoreRequest|DealerUpdateRequest $request): array
    {
        $data = $request->validated();
        $data['firm_name'] = ucwords(strtolower($data['firm_name']));
        $data['dealer_name'] = ucwords(strtolower($data['dealer_name']));
        $data['credit_limit'] = $data['credit_limit'] ?? 0;

        return $data;
    }

    /**
     * DLR000001-style codes. withTrashed() avoids reissuing a code that
     * belongs to a soft-deleted dealer just because it's hidden from the
     * default query; SUBSTRING position 4 works for both this app's new
     * "DLR" prefix and any pre-existing "ARC"-prefixed codes, since both
     * are 3 characters.
     */
    private function nextDealerCode(): string
    {
        $maxNumber = Dealer::withTrashed()
            ->selectRaw('MAX(CAST(SUBSTRING(dealer_code, 4) AS UNSIGNED)) as max_number')
            ->value('max_number');

        return 'DLR'.str_pad(((int) $maxNumber) + 1, 6, '0', STR_PAD_LEFT);
    }

    private function locationOptions(): array
    {
        return [
            'states' => State::where('status', true)->orderBy('name')->get(['id', 'name']),
            'districts' => District::where('status', true)->orderBy('name')->get(['id', 'name', 'state_id']),
            'talukas' => Taluka::where('status', true)->orderBy('name')->get(['id', 'name', 'district_id']),
            'villages' => Village::where('status', true)->orderBy('name')->get(['id', 'name', 'taluka_id']),
        ];
    }
}
