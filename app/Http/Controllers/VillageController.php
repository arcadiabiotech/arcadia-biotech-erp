<?php

namespace App\Http\Controllers;

use App\Models\Taluka;
use App\Models\Village;
use Illuminate\Http\Request;

class VillageController extends Controller
{
    /**
     * Display Village List
     */
    public function index(Request $request)
    {
        $search = $request->search;

        $villages = Village::with('taluka')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                      ->orWhereHas('taluka', function ($q) use ($search) {
                          $q->where('name', 'LIKE', "%{$search}%");
                      });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('villages.index', compact('villages'));
    }

    /**
     * Show Create Form
     */
    public function create()
    {
        $talukas = Taluka::where('status', true)
            ->orderBy('name')
            ->get();

        return view('villages.create', compact('talukas'));
    }

    /**
     * Store Village
     */
    public function store(Request $request)
    {
        $request->validate([
            'taluka_id' => 'required|exists:talukas,id',
            'name' => 'required|max:100',
        ]);

        Village::create([
            'taluka_id' => $request->taluka_id,
            'name' => ucwords(strtolower($request->name)),
            'status' => true,
        ]);

        return redirect()
            ->route('villages.index')
            ->with('success', 'Village Added Successfully.');
    }

    /**
     * Show Edit Form
     */
    public function edit(Village $village)
    {
        $talukas = Taluka::where('status', true)
            ->orderBy('name')
            ->get();

        return view('villages.edit', compact('village', 'talukas'));
    }

    /**
     * Update Village
     */
    public function update(Request $request, Village $village)
    {
        $request->validate([
            'taluka_id' => 'required|exists:talukas,id',
            'name' => 'required|max:100',
        ]);

        $village->update([
            'taluka_id' => $request->taluka_id,
            'name' => ucwords(strtolower($request->name)),
            'status' => $request->has('status'),
        ]);

        return redirect()
            ->route('villages.index')
            ->with('success', 'Village Updated Successfully.');
    }

    /**
     * Delete Village
     */
    public function destroy(Village $village)
    {
        $village->delete();

        return redirect()
            ->route('villages.index')
            ->with('success', 'Village Deleted Successfully.');
    }
}
