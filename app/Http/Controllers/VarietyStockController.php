<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\VarietyStock;
use Illuminate\Http\Request;

/**
 * Minimal actual-stock ledger for the Stock Reservation engine — Admin sets
 * how many plants of each variety actually exist; the reservation engine
 * derives Available Stock (Actual - Reserved) from it. Not a full Inventory
 * module: one row per variety, no batches/lots.
 */
class VarietyStockController extends Controller
{
    public function index()
    {
        foreach (Booking::VARIETIES as $variety) {
            VarietyStock::firstOrCreate(['variety' => $variety], ['actual_qty' => 0]);
        }

        $stocks = VarietyStock::orderBy('variety')->get();

        return view('variety-stocks.index', compact('stocks'));
    }

    public function update(Request $request, VarietyStock $varietyStock)
    {
        $data = $request->validate([
            'actual_qty' => ['required', 'integer', 'min:0'],
        ]);

        $varietyStock->update([
            'actual_qty' => $data['actual_qty'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', "Stock for {$varietyStock->variety} updated.");
    }
}
