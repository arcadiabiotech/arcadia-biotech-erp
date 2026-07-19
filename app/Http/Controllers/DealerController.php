<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use Illuminate\Http\Request;

class DealerController extends Controller
{
    /**
     * Dealer List
     */
    public function index(Request $request)
    {
        $search = $request->search;

        $dealers = Dealer::when($search, function ($query) use ($search) {

            $query->where('dealer_code', 'LIKE', "%{$search}%")
                  ->orWhere('dealer_name', 'LIKE', "%{$search}%")
                  ->orWhere('firm_name', 'LIKE', "%{$search}%")
                  ->orWhere('mobile', 'LIKE', "%{$search}%");

        })
        ->latest()
        ->paginate(15)
        ->withQueryString();

        return view('dealers.index', compact('dealers'));
    }

    /**
     * Add Dealer Form
     */
    public function create()
    {
        return view('dealers.create');
    }

    /**
     * Save Dealer
     */
    public function store(Request $request)
    {
        $request->validate([

            'firm_name'   => 'required|max:150',

            'dealer_name' => 'required|max:150',

            'mobile'      => 'required|digits:10|unique:dealers,mobile',

            'whatsapp'    => 'nullable|digits:10',

            'email'       => 'nullable|email',

        ]);

        // Auto Dealer Code
        $lastDealer = Dealer::latest()->first();

        if ($lastDealer) {

            $number = (int) substr($lastDealer->dealer_code,3);

            $dealerCode = 'ARC'.str_pad($number+1,6,'0',STR_PAD_LEFT);

        } else {

            $dealerCode = 'ARC000001';

        }

        Dealer::create([

            'dealer_code' => $dealerCode,

            'firm_name' => ucwords(strtolower($request->firm_name)),

            'dealer_name' => ucwords(strtolower($request->dealer_name)),

            'mobile' => $request->mobile,

            'whatsapp' => $request->whatsapp,

            'email' => $request->email,

            'gst_number' => $request->gst_number,

            'pan_number' => $request->pan_number,

            'address' => $request->address,

            'status' => true,

        ]);

        return redirect()
            ->route('dealers.index')
            ->with('success','Dealer Registered Successfully.');
    }

    /**
     * Edit Dealer
     */
    public function edit(Dealer $dealer)
    {
        return view('dealers.edit',compact('dealer'));
    }

    /**
     * Update Dealer
     */
    public function update(Request $request, Dealer $dealer)
    {
        $request->validate([

            'firm_name'   => 'required|max:150',

            'dealer_name' => 'required|max:150',

            'mobile'      => 'required|digits:10|unique:dealers,mobile,'.$dealer->id,

            'whatsapp'    => 'nullable|digits:10',

            'email'       => 'nullable|email',

        ]);

        $dealer->update([

            'firm_name' => ucwords(strtolower($request->firm_name)),

            'dealer_name' => ucwords(strtolower($request->dealer_name)),

            'mobile' => $request->mobile,

            'whatsapp' => $request->whatsapp,

            'email' => $request->email,

            'gst_number' => $request->gst_number,

            'pan_number' => $request->pan_number,

            'address' => $request->address,

        ]);

        return redirect()
            ->route('dealers.index')
            ->with('success','Dealer Updated Successfully.');
    }

    /**
     * Delete Dealer
     */
    public function destroy(Dealer $dealer)
    {
        $dealer->delete();

        return redirect()
            ->route('dealers.index')
            ->with('success','Dealer Deleted Successfully.');
    }
}