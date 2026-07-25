<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Dealer;
use App\Models\DealerAssignment;
use App\Models\RatingHistory;
use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use App\Support\TextCasing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
    ) {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $roleFilter = $request->string('role')->toString();
        $statusFilter = $request->string('status')->toString();
        $trashed = $request->boolean('trashed');

        $users = User::with(['role', 'dealer'])
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($roleFilter, fn ($query) => $query->whereHas('role', fn ($q) => $q->where('name', $roleFilter)))
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter === '1'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $roles = Role::where('status', true)->orderBy('display_name')->get();

        return view('users.index', compact('users', 'roles', 'trashed'));
    }

    public function create()
    {
        return view('users.form', [
            'user' => new User(['status' => true]),
            'roles' => $this->assignableRoles(),
            'dealers' => Dealer::orderBy('dealer_name')->get(),
            'assignedDealerIds' => [],
            'activity' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = Role::find($data['role_id']);

        abort_if(
            $role?->name === 'super-admin' && ! $request->user()->hasRole('super-admin'),
            403,
            'Only a Super Admin can create another Super Admin.'
        );

        // Registration rule: a Marketing User cannot be created until its
        // mobile number has been OTP-verified. Scoped to the Marketing
        // role specifically (the one flow the spec calls out) — other
        // roles created here are unaffected.
        if ($role?->name === 'marketing' && ! $this->otp->isVerified($data['mobile'], 'registration')) {
            throw ValidationException::withMessages([
                'mobile' => 'Please verify this mobile number via OTP before creating the marketing user.',
            ]);
        }

        $data['password'] = Hash::make($data['password']);

        if ($role?->name === 'marketing') {
            $data['mobile_verified_at'] = now();
        }

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        $user = DB::transaction(function () use ($data, $request, $role) {
            $user = User::create($data);

            if ($role?->name === 'marketing') {
                $this->syncMarketingDealers($user, $request->input('dealer_ids', []));
            }

            return $user;
        });

        if ($role?->name === 'marketing') {
            $this->otp->consume($data['mobile'], 'registration');
        }

        ActivityLog::record('users', $user->id, 'create', [], $user->toArray());

        return to_route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return view('users.form', [
            'user' => $user,
            'roles' => $this->assignableRoles(),
            'dealers' => Dealer::orderBy('dealer_name')->get(),
            'assignedDealerIds' => $user->dealerAssignments()->pluck('dealer_id')->all(),
            'activity' => ActivityLog::where('module', 'users')->where('record_id', $user->id)->latest()->limit(10)->get(),
            'ratingHistory' => RatingHistory::where('module', 'user')->where('rateable_id', $user->id)->latest('created_at')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        $role = Role::find($data['role_id']);

        abort_if(
            $role?->name === 'super-admin' && ! $request->user()->hasRole('super-admin'),
            403,
            'Only a Super Admin can promote a user to Super Admin.'
        );
        abort_if(
            $user->role?->name === 'super-admin' && ! $request->user()->hasRole('super-admin'),
            403,
            'Only a Super Admin can edit a Super Admin.'
        );

        $loggableKeys = array_diff(array_keys($data), ['password']);
        $original = $user->only($loggableKeys);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $data['profile_photo'] = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        DB::transaction(function () use ($user, $data, $request, $role) {
            $user->update($data);

            if ($role?->name === 'marketing') {
                $this->syncMarketingDealers($user, $request->input('dealer_ids', []));
            } else {
                $user->dealerAssignments->each->delete();
            }
        });

        ActivityLog::record('users', $user->id, 'update', $original, $user->fresh()->only($loggableKeys));

        return to_route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $user->dealerAssignments->each->delete();

        $user->delete();

        ActivityLog::record('users', $user->id, 'delete');

        return to_route('users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * Restore a soft-deleted user. Not part of the standard resourceful
     * methods, so authorization is checked explicitly rather than via
     * authorizeResource(). The route is bound withTrashed() so the model
     * still resolves despite the default SoftDeletingScope.
     */
    public function restore(User $user)
    {
        $this->authorize('restore', $user);

        $user->restore();

        ActivityLog::record('users', $user->id, 'update', [], [], 'User restored');

        return to_route('users.index')->with('success', 'User restored successfully.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'mobile' => ['required', 'digits:10'],
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'dealer_id' => ['nullable', Rule::exists('dealers', 'id')],
            'dealer_ids' => ['nullable', 'array'],
            'dealer_ids.*' => ['integer', Rule::exists('dealers', 'id')],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'status' => ['required', 'boolean'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
        ]);

        // dealer_ids and profile_photo aren't user columns; handled separately.
        unset($data['dealer_ids'], $data['profile_photo']);

        // Title-cased display name, same convention as the rest of the
        // app's proper-noun fields (CapitalizesNames trait).
        $data['name'] = TextCasing::titleCase($data['name']);

        $role = Role::find($data['role_id']);

        if ($role?->name === 'dealer') {
            validator($data, ['dealer_id' => ['required', Rule::exists('dealers', 'id')]])->validate();
        } else {
            $data['dealer_id'] = null;
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    /**
     * Roles selectable in the form: Admins may not assign the Super Admin
     * role to anyone (enforced again server-side in store/update — the UI
     * simply not offering it is a courtesy, not the actual guard).
     */
    private function assignableRoles()
    {
        return Role::where('status', true)
            ->when(! auth()->user()->hasRole('super-admin'), fn ($q) => $q->where('name', '!=', 'super-admin'))
            ->orderBy('display_name')
            ->get();
    }

    /**
     * Reconcile which dealers this marketing user manages: unassign
     * (soft delete) dealers no longer selected, assign newly selected
     * ones. The form gates already-assigned dealers behind an explicit
     * confirmation popup, so a dealer id reaching here is understood to be
     * an intentional reassignment — release it from its current owner and
     * hand it to this marketing user.
     */
    private function syncMarketingDealers(User $marketingUser, array $dealerIds): void
    {
        $dealerIds = array_map('intval', $dealerIds);

        $marketingUser->dealerAssignments()
            ->whereNotIn('dealer_id', $dealerIds)
            ->get()
            ->each->delete();

        $alreadyManaged = $marketingUser->dealerAssignments()->pluck('dealer_id')->all();

        foreach (array_diff($dealerIds, $alreadyManaged) as $dealerId) {
            DealerAssignment::where('dealer_id', $dealerId)->get()->each->delete();

            DealerAssignment::create([
                'dealer_id' => $dealerId,
                'marketing_user_id' => $marketingUser->id,
                'assigned_by' => auth()->id(),
                'assigned_date' => now(),
                'status' => true,
            ]);
        }
    }
}
