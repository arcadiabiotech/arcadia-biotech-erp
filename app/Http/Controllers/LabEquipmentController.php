<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabEquipmentStoreRequest;
use App\Http\Requests\LabEquipmentUpdateRequest;
use App\Models\ActivityLog;
use App\Models\LabEquipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LabEquipmentController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(LabEquipment::class, 'lab_equipment');
    }

    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $trashed = $request->boolean('trashed');

        $equipment = LabEquipment::query()
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('equipment_code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('lab-equipment.index', compact('equipment', 'trashed'));
    }

    public function create()
    {
        return view('lab-equipment.form', ['labEquipment' => new LabEquipment(['status' => 'active'])]);
    }

    public function store(LabEquipmentStoreRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $equipment = LabEquipment::create($data);

        ActivityLog::record('lab-equipment', $equipment->id, 'create', [], $equipment->toArray());

        return redirect()->route('lab-equipment.index')->with('success', 'Equipment added successfully.');
    }

    public function edit(LabEquipment $labEquipment)
    {
        return view('lab-equipment.form', compact('labEquipment'));
    }

    public function update(LabEquipmentUpdateRequest $request, LabEquipment $labEquipment)
    {
        $original = $labEquipment->toArray();

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $labEquipment->update($data);

        ActivityLog::record('lab-equipment', $labEquipment->id, 'update', $original, $labEquipment->fresh()->toArray());

        return redirect()->route('lab-equipment.index')->with('success', 'Equipment updated successfully.');
    }

    public function destroy(LabEquipment $labEquipment)
    {
        DB::transaction(function () use ($labEquipment) {
            $labEquipment->update(['deleted_by' => auth()->id()]);
            $labEquipment->delete();
        });

        ActivityLog::record('lab-equipment', $labEquipment->id, 'delete');

        return redirect()->route('lab-equipment.index')->with('success', 'Equipment deleted successfully.');
    }

    public function restore(LabEquipment $labEquipment)
    {
        $this->authorize('restore', $labEquipment);

        DB::transaction(function () use ($labEquipment) {
            $labEquipment->restore();
            $labEquipment->update(['deleted_by' => null]);
        });

        ActivityLog::record('lab-equipment', $labEquipment->id, 'update', [], [], 'Equipment restored');

        return redirect()->route('lab-equipment.index')->with('success', 'Equipment restored successfully.');
    }
}
