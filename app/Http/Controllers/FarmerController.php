<?php

namespace App\Http\Controllers;

use App\Http\Requests\FarmerStoreRequest;
use App\Http\Requests\FarmerUpdateRequest;
use App\Models\ActivityLog;
use App\Models\Dealer;
use App\Models\District;
use App\Models\Farmer;
use App\Models\State;
use App\Models\Taluka;
use App\Models\Village;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FarmerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Farmer::class, 'farmer');
    }

    /**
     * Farmer list. Search, advanced filters, pagination and role-based
     * scoping all happen here; eager loading avoids N+1 queries on the
     * dealer/location columns rendered per row.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $statusFilter = $request->string('status')->toString();
        $dealerFilter = $request->string('dealer')->toString();
        $stateFilter = $request->string('state')->toString();
        $soilFilter = $request->string('soil_type')->toString();
        $trashed = $request->boolean('trashed');

        $farmers = Farmer::with(['dealer', 'state', 'district'])
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('farmer_code', 'like', "%{$search}%")
                ->orWhere('farmer_name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")
                ->orWhere('aadhaar_no', 'like', "%{$search}%")))
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter === '1'))
            ->when($dealerFilter, fn ($query) => $query->where('dealer_id', $dealerFilter))
            ->when($stateFilter, fn ($query) => $query->where('state_id', $stateFilter))
            ->when($soilFilter, fn ($query) => $query->where('soil_type', $soilFilter))
            ->when($user->hasRole('marketing'), fn ($query) => $query->whereHas('dealer.assignment', fn ($q) => $q->where('marketing_user_id', $user->id)))
            ->when($user->hasRole('dealer'), fn ($query) => $query->where('dealer_id', $user->dealer_id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('farmers.index', [
            'farmers' => $farmers,
            'dealers' => $this->visibleDealers($user),
            'states' => State::where('status', true)->orderBy('name')->get(),
            'soilTypes' => Farmer::SOIL_TYPES,
            'trashed' => $trashed,
        ]);
    }

    public function create(Request $request)
    {
        return view('farmers.form', array_merge(
            ['farmer' => new Farmer(['status' => true])],
            $this->locationOptions(),
            ['dealers' => $this->visibleDealers($request->user())]
        ));
    }

    public function store(FarmerStoreRequest $request)
    {
        $data = $this->prepared($request);
        $data['farmer_code'] = $this->nextFarmerCode();
        $data['created_by'] = $request->user()->id;

        $farmer = Farmer::create($data);

        ActivityLog::record('farmers', $farmer->id, 'create', [], $farmer->toArray());

        return redirect()->route('farmers.index')->with('success', 'Farmer registered successfully.');
    }

    /**
     * Farmer profile page: details, timeline, audit history, and
     * placeholders for the future documents/plantation/booking/ledger modules.
     */
    public function show(Farmer $farmer)
    {
        $farmer->load(['dealer.assignment.marketingUser', 'state', 'district', 'taluka', 'village', 'createdBy', 'updatedBy']);

        $activity = ActivityLog::where('module', 'farmers')->where('record_id', $farmer->id)->latest()->limit(30)->get();

        return view('farmers.show', compact('farmer', 'activity'));
    }

    public function edit(Farmer $farmer, Request $request)
    {
        $dealers = $this->visibleDealers($request->user());

        // The farmer's current dealer must remain selectable even if it
        // wouldn't otherwise be offered (e.g. an Admin reassigned it away
        // from this Marketing user after the farmer was created).
        if (! $dealers->contains('id', $farmer->dealer_id)) {
            $dealers = $dealers->push(Dealer::find($farmer->dealer_id))->filter();
        }

        return view('farmers.form', array_merge(
            ['farmer' => $farmer],
            $this->locationOptions(),
            ['dealers' => $dealers->sortBy('dealer_name')->values()]
        ));
    }

    public function update(FarmerUpdateRequest $request, Farmer $farmer)
    {
        $original = $farmer->toArray();

        $data = $this->prepared($request);
        $data['updated_by'] = $request->user()->id;

        $farmer->update($data);

        ActivityLog::record('farmers', $farmer->id, 'update', $original, $farmer->fresh()->toArray());

        return redirect()->route('farmers.index')->with('success', 'Farmer updated successfully.');
    }

    public function destroy(Farmer $farmer)
    {
        DB::transaction(function () use ($farmer) {
            $farmer->update(['deleted_by' => auth()->id()]);
            $farmer->delete();
        });

        ActivityLog::record('farmers', $farmer->id, 'delete');

        return redirect()->route('farmers.index')->with('success', 'Farmer deleted successfully.');
    }

    /**
     * Restore a soft-deleted farmer. Not part of the standard resourceful
     * methods, so authorization is checked explicitly. The route is bound
     * withTrashed() so the model resolves despite the SoftDeletingScope.
     */
    public function restore(Farmer $farmer)
    {
        $this->authorize('restore', $farmer);

        DB::transaction(function () use ($farmer) {
            $farmer->restore();
            $farmer->update(['deleted_by' => null]);
        });

        ActivityLog::record('farmers', $farmer->id, 'update', [], [], 'Farmer restored');

        return redirect()->route('farmers.index')->with('success', 'Farmer restored successfully.');
    }

    private function prepared(FarmerStoreRequest|FarmerUpdateRequest $request): array
    {
        $data = $request->validated();
        $data['farmer_name'] = ucwords(strtolower($data['farmer_name']));

        if (! empty($data['father_name'])) {
            $data['father_name'] = ucwords(strtolower($data['father_name']));
        }

        return $data;
    }

    /**
     * FAR000001-style codes. withTrashed() avoids reissuing a code that
     * belongs to a soft-deleted farmer just because it's hidden from the
     * default query.
     */
    private function nextFarmerCode(): string
    {
        $maxNumber = Farmer::withTrashed()
            ->selectRaw('MAX(CAST(SUBSTRING(farmer_code, 4) AS UNSIGNED)) as max_number')
            ->value('max_number');

        return 'FAR'.str_pad(((int) $maxNumber) + 1, 6, '0', STR_PAD_LEFT);
    }

    private function locationOptions(): array
    {
        return [
            'states' => State::where('status', true)->orderBy('name')->get(['id', 'name']),
            'districts' => District::where('status', true)->orderBy('name')->get(['id', 'name', 'state_id']),
            'talukas' => Taluka::where('status', true)->orderBy('name')->get(['id', 'name', 'district_id']),
            'villages' => Village::where('status', true)->orderBy('name')->get(['id', 'name', 'taluka_id']),
            'soilTypes' => Farmer::SOIL_TYPES,
            'irrigationTypes' => Farmer::IRRIGATION_TYPES,
        ];
    }

    private function visibleDealers($user)
    {
        return Dealer::when($user->hasRole('marketing'), fn ($q) => $q->whereHas('assignment', fn ($qq) => $qq->where('marketing_user_id', $user->id)))
            ->when($user->hasRole('dealer'), fn ($q) => $q->where('id', $user->dealer_id))
            ->orderBy('dealer_name')
            ->get(['id', 'dealer_name']);
    }
}
