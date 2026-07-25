<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="mb-8">
            <a href="{{ $dispatch->exists ? route('dispatches.show', $dispatch) : route('dispatches.create') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $dispatch->exists ? 'Edit dispatch' : 'New dispatch' }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $dispatch->exists ? $dispatch->dispatch_no : 'A dispatch number and challan number are generated automatically on save.' }}</p>
        </div>

        <form
            x-data="{
                gps: '{{ old('gps_location', $dispatch->gps_location) }}',
                capturing: false,
                captureGps() {
                    if (! navigator.geolocation) { alert('Geolocation is not supported by this browser.'); return; }
                    this.capturing = true;
                    navigator.geolocation.getCurrentPosition(
                        (pos) => { this.gps = pos.coords.latitude.toFixed(6) + ',' + pos.coords.longitude.toFixed(6); this.capturing = false; },
                        () => { alert('Unable to capture location.'); this.capturing = false; }
                    );
                },
            }"
            method="POST" action="{{ $dispatch->exists ? route('dispatches.update', $dispatch) : route('dispatches.store') }}"
            enctype="multipart/form-data"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($dispatch->exists) @method('PUT') @endif

            @if(! $dispatch->exists)
                <input type="hidden" name="vehicle_assignment_id" value="{{ $assignment->id }}">

                <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Vehicle</div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                    <p class="font-semibold text-slate-800">{{ $assignment->vehicle->vehicle_no }} — {{ $assignment->dispatchPlan->plan_no }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $assignment->driver_name ?? 'No driver set' }}@if($assignment->driver_mobile) · {{ $assignment->driver_mobile }} @endif</p>
                </div>

                <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Bookings on this vehicle</div>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Booking / Dealer</th>
                                <th class="px-3 py-2 text-right">Planned</th>
                                <th class="px-3 py-2 text-right">Dispatched</th>
                                <th class="px-3 py-2 text-right">Remaining</th>
                                <th class="px-3 py-2 text-left">Dispatch qty</th>
                                <th class="px-3 py-2 text-left">Extra</th>
                                <th class="px-3 py-2 text-left">Per crate</th>
                                <th class="px-3 py-2 text-left">Batch</th>
                                <th class="px-3 py-2 text-left">Age</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($assignment->items as $item)
                                @if($item->remaining_qty > 0)
                                    <tr>
                                        <td class="px-3 py-2">
                                            <p class="font-semibold text-slate-800">{{ $item->booking->booking_no }}</p>
                                            <p class="text-xs text-slate-500">{{ $item->booking->dealer?->dealer_name }} / {{ $item->booking->farmer?->farmer_name }}</p>
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ number_format($item->dispatch_qty) }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($item->dispatched_qty) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold text-slate-800">{{ number_format($item->remaining_qty) }}</td>
                                        <td class="px-3 py-2"><input type="number" min="0" max="{{ $item->remaining_qty }}" name="lines[{{ $item->id }}][qty]" value="{{ old("lines.{$item->id}.qty", $item->remaining_qty) }}" class="w-24 rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">@error("lines.{$item->id}.qty")<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</td>
                                        <td class="px-3 py-2"><input type="number" min="0" name="lines[{{ $item->id }}][extra_qty]" value="{{ old("lines.{$item->id}.extra_qty", 0) }}" class="w-20 rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></td>
                                        <td class="px-3 py-2"><input type="number" min="1" name="lines[{{ $item->id }}][qty_per_crate]" value="{{ old("lines.{$item->id}.qty_per_crate", 40) }}" class="w-20 rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></td>
                                        <td class="px-3 py-2"><input name="lines[{{ $item->id }}][batch_number]" value="{{ old("lines.{$item->id}.batch_number") }}" class="w-24 rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></td>
                                        <td class="px-3 py-2"><input name="lines[{{ $item->id }}][plant_age]" value="{{ old("lines.{$item->id}.plant_age") }}" placeholder="60 days" class="w-24 rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('lines')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
            @else
                <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Bookings on this dispatch</div>
                <div class="divide-y divide-slate-100 rounded-xl border border-slate-200 text-sm">
                    @foreach($dispatch->lines as $line)
                        <div class="flex items-center justify-between p-3">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $line->booking->booking_no }}</p>
                                <p class="text-xs text-slate-500">{{ $line->dealer?->dealer_name }} / {{ $line->farmer?->farmer_name }}</p>
                            </div>
                            <span class="text-slate-600">{{ number_format($line->total_qty) }} plants</span>
                        </div>
                    @endforeach
                </div>

                <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Vehicle</div>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Vehicle</label>
                        <select name="vehicle_id" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Not assigned yet —</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" @selected(old('vehicle_id', $dispatch->vehicle_id) == $vehicle->id)>{{ $vehicle->vehicle_no }} ({{ $vehicle->vehicle_type }})</option>
                            @endforeach
                        </select>
                        @error('vehicle_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            @endif

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Driver</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div><label class="text-sm font-semibold text-slate-700">Driver name</label><input name="driver_name" value="{{ old('driver_name', $dispatch->driver_name ?? $assignment->driver_name ?? '') }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('driver_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Driver mobile</label><input name="driver_mobile" value="{{ old('driver_mobile', $dispatch->driver_mobile ?? $assignment->driver_mobile ?? '') }}" maxlength="15" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('driver_mobile')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">LR number</label><input name="lr_number" value="{{ old('lr_number', $dispatch->lr_number) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Lorry receipt no.">@error('lr_number')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Dispatch details</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div><label class="text-sm font-semibold text-slate-700">Dispatch date</label><input type="date" name="dispatch_date" value="{{ old('dispatch_date', optional($dispatch->dispatch_date)->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('dispatch_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Expected delivery</label><input type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date', optional($dispatch->expected_delivery_date)->format('Y-m-d')) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('expected_delivery_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">GPS location</div>
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1"><label class="text-sm font-semibold text-slate-700">Coordinates</label><input name="gps_location" x-model="gps" placeholder="lat,lng" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('gps_location')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <button type="button" @click="captureGps()" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" x-text="capturing ? 'Locating…' : 'Capture current location'"></button>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Documents</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Photo</label>
                    <input type="file" name="photo" accept="image/*" class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                    @if($dispatch->photo)<p class="mt-1 text-xs text-slate-500">Current: <a href="{{ \Illuminate\Support\Facades\Storage::url($dispatch->photo) }}" target="_blank" class="text-blue-600 hover:underline">view photo</a></p>@endif
                    @error('photo')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Signature</label>
                    <input type="file" name="signature" accept="image/*" class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                    @if($dispatch->signature)<p class="mt-1 text-xs text-slate-500">Current: <a href="{{ \Illuminate\Support\Facades\Storage::url($dispatch->signature) }}" target="_blank" class="text-blue-600 hover:underline">view signature</a></p>@endif
                    @error('signature')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Notes</div>
            <div><textarea name="remarks" rows="3" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks', $dispatch->remarks) }}</textarea>@error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ $dispatch->exists ? route('dispatches.show', $dispatch) : route('dispatches.create') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $dispatch->exists ? 'Save changes' : 'Create dispatch' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
