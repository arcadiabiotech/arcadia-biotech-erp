<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Taluka;
use Illuminate\Http\Request;

class TalukaController extends Controller
{
    /**
     * Display Taluka List
     */
    public function index(Request $request)
    {
        $search = $request->search;

        $talukas = Taluka::with('district')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                      ->orWhereHas('district', function ($q) use ($search) {
                          $q->where('name', 'LIKE', "%{$search}%");
                      });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('talukas.index', compact('talukas'));
    }

    /**
     * Show Create Form
     */
    public function create()
    {
        $districts = District::where('status', true)
            ->orderBy('name')
            ->get();

        return view('talukas.create', compact('districts'));
    }

    /**
     * Store Taluka
     */
    public function store(Request $request)
    {
        $request->validate([
            'district_id' => 'required|exists:districts,id',
            'name' => 'required|max:100',
        ]);

        Taluka::create([
            'district_id' => $request->district_id,
            'name' => ucwords(strtolower($request->name)),
            'status' => true,
        ]);

        return redirect()
            ->route('talukas.index')
            ->with('success', 'Taluka Added Successfully.');
    }

    /**
     * Show Edit Form
     */
    public function edit(Taluka $taluka)
    {
        $districts = District::where('status', true)
            ->orderBy('name')
            ->get();

        return view('talukas.edit', compact('taluka', 'districts'));
    }

    /**
     * Update Taluka
     */
    public function update(Request $request, Taluka $taluka)
    {
        $request->validate([
            'district_id' => 'required|exists:districts,id',
            'name' => 'required|max:100',
        ]);

        $taluka->update([
            'district_id' => $request->district_id,
            'name' => ucwords(strtolower($request->name)),
            'status' => $request->has('status'),
        ]);

        return redirect()
            ->route('talukas.index')
            ->with('success', 'Taluka Updated Successfully.');
    }

    /**
     * Delete Taluka
     */
    public function destroy(Taluka $taluka)
    {
        $taluka->delete();

        return redirect()
            ->route('talukas.index')
            ->with('success', 'Taluka Deleted Successfully.');
    }
}
