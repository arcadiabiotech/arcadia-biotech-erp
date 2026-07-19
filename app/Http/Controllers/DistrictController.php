<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\State;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    /**
     * Display District List
     */
    public function index(Request $request)
    {
        $search = $request->search;

        $districts = District::with('state')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                      ->orWhereHas('state', function ($q) use ($search) {
                          $q->where('name', 'LIKE', "%{$search}%");
                      });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('districts.index', compact('districts'));
    }

    /**
     * Show Create Form
     */
    public function create()
    {
        $states = State::where('status', true)
            ->orderBy('name')
            ->get();

        return view('districts.create', compact('states'));
    }

    /**
     * Store District
     */
    public function store(Request $request)
    {
        $request->validate([
            'state_id' => 'required|exists:states,id',
            'name' => 'required|max:100',
        ]);

        District::create([
            'state_id' => $request->state_id,
            'name' => ucwords(strtolower($request->name)),
            'status' => true,
        ]);

        return redirect()
            ->route('districts.index')
            ->with('success', 'District Added Successfully.');
    }

    /**
     * Show Edit Form
     */
    public function edit(District $district)
    {
        $states = State::where('status', true)
            ->orderBy('name')
            ->get();

        return view('districts.edit', compact('district', 'states'));
    }

    /**
     * Update District
     */
    public function update(Request $request, District $district)
    {
        $request->validate([
            'state_id' => 'required|exists:states,id',
            'name' => 'required|max:100',
        ]);

        $district->update([
            'state_id' => $request->state_id,
            'name' => ucwords(strtolower($request->name)),
            'status' => $request->has('status'),
        ]);

        return redirect()
            ->route('districts.index')
            ->with('success', 'District Updated Successfully.');
    }

    /**
     * Delete District
     */
    public function destroy(District $district)
    {
        $district->delete();

        return redirect()
            ->route('districts.index')
            ->with('success', 'District Deleted Successfully.');
    }
}