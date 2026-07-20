<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <a href="{{ route('farmers.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to farmers</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $farmer->exists ? 'Edit farmer' : 'Register farmer' }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $farmer->exists ? $farmer->farmer_code : 'A farmer code is generated automatically on save.' }}</p>
        </div>

        <form
            x-data="{
                districts: {{ Js::from($districts->map(fn ($d) => ['id' => (string) $d->id, 'name' => $d->name, 'state_id' => (string) $d->state_id])) }},
                talukas: {{ Js::from($talukas->map(fn ($t) => ['id' => (string) $t->id, 'name' => $t->name, 'district_id' => (string) $t->district_id])) }},
                villages: {{ Js::from($villages->map(fn ($v) => ['id' => (string) $v->id, 'name' => $v->name, 'taluka_id' => (string) $v->taluka_id])) }},
                stateId: '{{ old('state_id', $farmer->state_id) }}',
                districtId: '{{ old('district_id', $farmer->district_id) }}',
                talukaId: '{{ old('taluka_id', $farmer->taluka_id) }}',
                get visibleDistricts() { return this.districts.filter(d => d.state_id === this.stateId) },
                get visibleTalukas() { return this.talukas.filter(t => t.district_id === this.districtId) },
                get visibleVillages() { return this.villages.filter(v => v.taluka_id === this.talukaId) },
            }"
            method="POST" action="{{ $farmer->exists ? route('farmers.update', $farmer) : route('farmers.store') }}"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($farmer->exists) @method('PUT') @endif

            <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Basic details</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div><label class="text-sm font-semibold text-slate-700">Farmer name</label><input name="farmer_name" value="{{ old('farmer_name', $farmer->farmer_name) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('farmer_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Father's name</label><input name="father_name" value="{{ old('father_name', $farmer->father_name) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('father_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Dealer</label>
                    <select name="dealer_id" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select dealer —</option>
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected(old('dealer_id', $farmer->dealer_id) == $dealer->id)>{{ $dealer->dealer_name }}</option>
                        @endforeach
                    </select>
                    @error('dealer_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Status</label>
                    <select name="status" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="1" @selected(old('status', $farmer->status) == 1)>Active</option>
                        <option value="0" @selected(old('status', $farmer->status) == 0)>Inactive</option>
                    </select>
                    @error('status')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div><label class="text-sm font-semibold text-slate-700">Mobile</label><input name="mobile" value="{{ old('mobile', $farmer->mobile) }}" required maxlength="10" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('mobile')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Alternate mobile</label><input name="alternate_mobile" value="{{ old('alternate_mobile', $farmer->alternate_mobile) }}" maxlength="10" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('alternate_mobile')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Aadhaar number</label><input name="aadhaar_no" value="{{ old('aadhaar_no', $farmer->aadhaar_no) }}" maxlength="12" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('aadhaar_no')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Location</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-semibold text-slate-700">State</label>
                    <select name="state_id" x-model="stateId" @change="districtId = ''; talukaId = ''" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select state —</option>
                        @foreach($states as $state)<option value="{{ $state->id }}">{{ $state->name }}</option>@endforeach
                    </select>
                    @error('state_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">District</label>
                    <select name="district_id" x-model="districtId" @change="talukaId = ''" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select district —</option>
                        <template x-for="district in visibleDistricts" :key="district.id"><option :value="district.id" x-text="district.name" :selected="district.id === districtId"></option></template>
                    </select>
                    @error('district_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Taluka</label>
                    <select name="taluka_id" x-model="talukaId" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select taluka —</option>
                        <template x-for="taluka in visibleTalukas" :key="taluka.id"><option :value="taluka.id" x-text="taluka.name" :selected="taluka.id === talukaId"></option></template>
                    </select>
                    @error('taluka_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Village</label>
                    <select name="village_id" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select village —</option>
                        <template x-for="village in visibleVillages" :key="village.id"><option :value="village.id" x-text="village.name" :selected="village.id === '{{ old('village_id', $farmer->village_id) }}'"></option></template>
                    </select>
                    @error('village_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="text-sm font-semibold text-slate-700">PIN code</label><input name="pincode" value="{{ old('pincode', $farmer->pincode) }}" maxlength="6" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('pincode')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Address</label><textarea name="address" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('address', $farmer->address) }}</textarea>@error('address')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Farm details</div>
            <div class="grid gap-6 sm:grid-cols-3">
                <div><label class="text-sm font-semibold text-slate-700">Farm area (acres)</label><input type="number" step="0.01" min="0" name="farm_area" value="{{ old('farm_area', $farmer->farm_area) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('farm_area')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Soil type</label>
                    <select name="soil_type" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select —</option>
                        @foreach($soilTypes as $soil)<option value="{{ $soil }}" @selected(old('soil_type', $farmer->soil_type) === $soil)>{{ $soil }}</option>@endforeach
                    </select>
                    @error('soil_type')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Irrigation type</label>
                    <select name="irrigation_type" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select —</option>
                        @foreach($irrigationTypes as $irrigation)<option value="{{ $irrigation }}" @selected(old('irrigation_type', $farmer->irrigation_type) === $irrigation)>{{ $irrigation }}</option>@endforeach
                    </select>
                    @error('irrigation_type')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Notes</div>
            <div><textarea name="remarks" rows="3" placeholder="Internal remarks" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks', $farmer->remarks) }}</textarea>@error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('farmers.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $farmer->exists ? 'Save changes' : 'Register farmer' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
