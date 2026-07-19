<?php

namespace App\Http\Controllers;

use App\Http\Requests\StateStoreRequest;
use App\Http\Requests\StateUpdateRequest;
use App\Models\State;
use Illuminate\Http\Request;

class StateController extends Controller
{
    /**
     * Display States
     */
    public function index(Request $request)
    {
        $search = $request->search;

        $states = State::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('states.index', compact('states'));
    }

    /**
     * Show Create Form
     */
    public function create()
    {
        return view('states.create');
    }

    /**
     * Store State
     */
    public function store(StateStoreRequest $request)
    {
        State::create([
            'name'   => ucwords(strtolower($request->name)),
            'code'   => strtoupper($request->code),
            'status' => true,
        ]);

        return redirect()
            ->route('states.index')
            ->with('success', 'State created successfully.');
    }

    /**
     * Show Edit Form
     */
    public function edit(State $state)
    {
        return view('states.edit', compact('state'));
    }

    /**
     * Update State
     */
    public function update(StateUpdateRequest $request, State $state)
    {
        $state->update([
            'name'   => ucwords(strtolower($request->name)),
            'code'   => strtoupper($request->code),
            'status' => $request->boolean('status'),
        ]);

        return redirect()
            ->route('states.index')
            ->with('success', 'State updated successfully.');
    }

    /**
     * Delete State
     */
    public function destroy(State $state)
    {
        $state->delete();

        return redirect()
            ->route('states.index')
            ->with('success', 'State deleted successfully.');
    }
}