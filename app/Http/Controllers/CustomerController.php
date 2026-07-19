<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    // Customer List
    public function index(Request $request)
    {
        $search = $request->search;

        $customers = Customer::when($search, function ($query) use ($search) {
            $query->where('farmer_name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")
                ->orWhere('village', 'like', "%{$search}%")
                ->orWhere('district', 'like', "%{$search}%")
                ->orWhere('taluka', 'like', "%{$search}%")
                ->orWhere('state', 'like', "%{$search}%");
        })
        ->latest()
        ->paginate(10)
        ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    // Add Customer Form
    public function create()
    {
        return view('customers.create');
    }

    // Save Customer
    public function store(Request $request)
    {
        $request->validate([
            'farmer_name' => 'required|max:150',
            'mobile'      => 'required|digits:10|unique:customers,mobile',
            'village'     => 'required',
            'taluka'      => 'required',
            'district'    => 'required',
            'state'       => 'required',
            'variety'     => 'required',
        ]);

        $data = $request->all();

        $data['farmer_name'] = ucwords(strtolower($data['farmer_name']));
        $data['village'] = ucwords(strtolower($data['village']));
        $data['taluka'] = ucwords(strtolower($data['taluka']));
        $data['district'] = ucwords(strtolower($data['district']));
        $data['state'] = ucwords(strtolower($data['state']));
        $data['variety'] = strtoupper($data['variety']);

        Customer::create($data);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer Added Successfully.');
    }

    // Edit Customer Form
    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    // Update Customer
    public function update(Request $request, Customer $customer)
    {
        $request->validate([
            'farmer_name' => 'required|max:150',
            'mobile'      => 'required|digits:10|unique:customers,mobile,' . $customer->id,
            'village'     => 'required',
            'taluka'      => 'required',
            'district'    => 'required',
            'state'       => 'required',
            'variety'     => 'required',
        ]);

        $data = $request->all();

        $data['farmer_name'] = ucwords(strtolower($data['farmer_name']));
        $data['village'] = ucwords(strtolower($data['village']));
        $data['taluka'] = ucwords(strtolower($data['taluka']));
        $data['district'] = ucwords(strtolower($data['district']));
        $data['state'] = ucwords(strtolower($data['state']));
        $data['variety'] = strtoupper($data['variety']);

        $customer->update($data);
        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer Updated Successfully.');
    }

    // Delete Customer
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer Deleted Successfully.');
    }
}