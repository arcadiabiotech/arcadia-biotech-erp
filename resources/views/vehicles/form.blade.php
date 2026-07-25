<x-app-layout>
    <div class="mx-auto max-w-3xl">
        <div class="mb-8">
            <a href="{{ route('vehicles.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to vehicles</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $vehicle->exists ? 'Edit vehicle' : 'Add vehicle' }}</h1>
        </div>

        @if(($returnDispatchPlanId ?? null))
            <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700">Once saved, this vehicle will be assigned straight back to that dispatch plan.</div>
        @endif

        <form method="POST" action="{{ $vehicle->exists ? route('vehicles.update', $vehicle) : route('vehicles.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($vehicle->exists) @method('PUT') @endif
            @if(($returnDispatchPlanId ?? null))<input type="hidden" name="return_dispatch_plan_id" value="{{ $returnDispatchPlanId }}">@endif

            <div class="grid gap-6 sm:grid-cols-2">
                <div><label class="text-sm font-semibold text-slate-700">Vehicle number</label><input name="vehicle_no" value="{{ old('vehicle_no', $vehicle->vehicle_no) }}" required class="mt-2 block w-full rounded-xl border-slate-300 uppercase shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('vehicle_no')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Vehicle type</label><input name="vehicle_type" value="{{ old('vehicle_type', $vehicle->vehicle_type) }}" placeholder="e.g. Truck, Tempo, Pickup" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('vehicle_type')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Capacity</label><input name="capacity" value="{{ old('capacity', $vehicle->capacity) }}" placeholder="e.g. 5 Ton" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('capacity')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Transport company</label><input name="transport_company" value="{{ old('transport_company', $vehicle->transport_company) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('transport_company')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Driver name</label><input name="driver_name" value="{{ old('driver_name', $vehicle->driver_name) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('driver_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Driver mobile</label><input name="driver_mobile" value="{{ old('driver_mobile', $vehicle->driver_mobile) }}" maxlength="15" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('driver_mobile')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Cost per KM (₹) <span class="text-rose-500">*</span></label><input type="number" step="0.01" min="0" name="cost_per_km" value="{{ old('cost_per_km', $vehicle->cost_per_km) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('cost_per_km')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Driver allowance (₹) <span class="font-normal text-slate-400">(optional)</span></label><input type="number" step="0.01" min="0" name="driver_allowance" value="{{ old('driver_allowance', $vehicle->driver_allowance) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('driver_allowance')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Fuel type <span class="font-normal text-slate-400">(optional)</span></label><input name="fuel_type" value="{{ old('fuel_type', $vehicle->fuel_type) }}" placeholder="e.g. Diesel, Petrol, CNG" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('fuel_type')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Average mileage (km/l) <span class="font-normal text-slate-400">(optional)</span></label><input type="number" step="0.01" min="0" name="average_mileage" value="{{ old('average_mileage', $vehicle->average_mileage) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('average_mileage')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Status</label>
                    <select name="status" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="1" @selected(old('status', $vehicle->status ?? true) == 1)>Active</option>
                        <option value="0" @selected(old('status', $vehicle->status) == 0)>Inactive</option>
                    </select>
                    @error('status')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('vehicles.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $vehicle->exists ? 'Save changes' : 'Add vehicle' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
