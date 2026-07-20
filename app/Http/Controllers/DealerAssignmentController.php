<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\DealerAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DealerAssignmentController extends Controller
{
    /**
     * Assignment list.
     * Admins see everything; Marketing users see only their own assigned dealers.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();

        $assignments = DealerAssignment::with(['dealer', 'marketingUser', 'assignedBy'])
            ->when($user->hasRole('marketing'), fn ($query) => $query->where('marketing_user_id', $user->id))
            ->when($search, fn ($query) => $query->whereHas('dealer', fn ($q) => $q
                ->where('dealer_name', 'like', "%{$search}%")
                ->orWhere('firm_name', 'like', "%{$search}%")
                ->orWhere('dealer_code', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dealer-assignments.index', compact('assignments'));
    }

    /**
     * Assign dealer form.
     */
    public function create()
    {
        return view('dealer-assignments.form', [
            'assignment' => new DealerAssignment(['assigned_date' => now()->toDateString(), 'status' => true]),
            'dealers' => $this->availableDealers(),
            'marketingUsers' => $this->marketingUsers(),
        ]);
    }

    /**
     * Save assignment.
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['assigned_by'] = $request->user()->id;

        DealerAssignment::create($data);

        return to_route('dealer-assignments.index')->with('success', 'Dealer assigned successfully.');
    }

    /**
     * Edit assignment.
     */
    public function edit(DealerAssignment $dealerAssignment)
    {
        return view('dealer-assignments.form', [
            'assignment' => $dealerAssignment,
            'dealers' => $this->availableDealers($dealerAssignment),
            'marketingUsers' => $this->marketingUsers(),
        ]);
    }

    /**
     * Update assignment.
     */
    public function update(Request $request, DealerAssignment $dealerAssignment)
    {
        $data = $this->validated($request, $dealerAssignment);

        $dealerAssignment->update($data);

        return to_route('dealer-assignments.index')->with('success', 'Assignment updated successfully.');
    }

    /**
     * Delete assignment.
     */
    public function destroy(DealerAssignment $dealerAssignment)
    {
        $dealerAssignment->delete();

        return to_route('dealer-assignments.index')->with('success', 'Assignment removed successfully.');
    }

    /**
     * Validate the request. dealer_id must be unique across assignments so a
     * dealer can belong to only one marketing user.
     */
    private function validated(Request $request, ?DealerAssignment $assignment = null): array
    {
        return $request->validate([
            'dealer_id' => [
                'required',
                Rule::exists('dealers', 'id'),
                Rule::unique('dealer_assignments', 'dealer_id')
                    ->where(fn ($query) => $query->whereNull('deleted_at'))
                    ->ignore($assignment?->id),
            ],
            'marketing_user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role_id', $this->marketingRoleIds())),
            ],
            'assigned_date' => ['required', 'date'],
            'status' => ['required', 'boolean'],
        ]);
    }

    /**
     * Dealers with no active assignment (plus the one being edited).
     */
    private function availableDealers(?DealerAssignment $assignment = null)
    {
        return Dealer::whereDoesntHave('assignment')
            ->when($assignment, fn ($query) => $query->orWhere('id', $assignment->dealer_id))
            ->orderBy('dealer_name')
            ->get();
    }

    /**
     * Users belonging to the Marketing role.
     */
    private function marketingUsers()
    {
        return User::whereIn('role_id', $this->marketingRoleIds())
            ->where('status', true)
            ->orderBy('name')
            ->get();
    }

    private function marketingRoleIds(): array
    {
        return \App\Models\Role::where('name', 'marketing')->pluck('id')->all();
    }
}
