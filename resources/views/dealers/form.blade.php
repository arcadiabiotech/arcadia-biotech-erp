<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <a href="{{ route('dealers.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to dealers</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $dealer->exists ? 'Edit dealer' : 'Register dealer' }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $dealer->exists ? $dealer->dealer_code : 'A dealer code is generated automatically on save.' }}</p>
        </div>

        <form
            x-data="{
                districts: {{ Js::from($districts->map(fn ($d) => ['id' => (string) $d->id, 'name' => $d->name, 'state_id' => (string) $d->state_id])) }},
                talukas: {{ Js::from($talukas->map(fn ($t) => ['id' => (string) $t->id, 'name' => $t->name, 'district_id' => (string) $t->district_id])) }},
                villages: {{ Js::from($villages->map(fn ($v) => ['id' => (string) $v->id, 'name' => $v->name, 'taluka_id' => (string) $v->taluka_id])) }},
                stateId: '{{ old('state_id', $dealer->state_id) }}',
                districtId: '{{ old('district_id', $dealer->district_id) }}',
                talukaId: '{{ old('taluka_id', $dealer->taluka_id) }}',
                get visibleDistricts() { return this.districts.filter(d => d.state_id === this.stateId) },
                get visibleTalukas() { return this.talukas.filter(t => t.district_id === this.districtId) },
                get visibleVillages() { return this.villages.filter(v => v.taluka_id === this.talukaId) },
            }"
            method="POST" action="{{ $dealer->exists ? route('dealers.update', $dealer) : route('dealers.store') }}"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($dealer->exists) @method('PUT') @endif

            <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Basic details</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div><label class="text-sm font-semibold text-slate-700">Firm name</label><input name="firm_name" value="{{ old('firm_name', $dealer->firm_name) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('firm_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Dealer name</label><input name="dealer_name" value="{{ old('dealer_name', $dealer->dealer_name) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('dealer_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Mobile</label><input name="mobile" value="{{ old('mobile', $dealer->mobile) }}" required maxlength="10" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('mobile')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">WhatsApp</label><input name="whatsapp" value="{{ old('whatsapp', $dealer->whatsapp) }}" maxlength="10" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('whatsapp')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Email</label><input type="email" name="email" value="{{ old('email', $dealer->email) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('email')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Status</label>
                    <select name="status" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="1" @selected(old('status', $dealer->status) == 1)>Active</option>
                        <option value="0" @selected(old('status', $dealer->status) == 0)>Inactive</option>
                    </select>
                    @error('status')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Compliance</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div><label class="text-sm font-semibold text-slate-700">GST number</label><input name="gst_number" value="{{ old('gst_number', $dealer->gst_number) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('gst_number')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">PAN number</label><input name="pan_number" value="{{ old('pan_number', $dealer->pan_number) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('pan_number')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Agreement date</label><input type="date" name="agreement_date" value="{{ old('agreement_date', optional($dealer->agreement_date)->format('Y-m-d')) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('agreement_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Credit limit (₹)</label><input type="number" step="0.01" min="0" name="credit_limit" value="{{ old('credit_limit', $dealer->credit_limit) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('credit_limit')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Location</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-semibold text-slate-700">State</label>
                    <select name="state_id" x-model="stateId" @change="districtId = ''; talukaId = ''" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select state —</option>
                        @foreach($states as $state)<option value="{{ $state->id }}">{{ $state->name }}</option>@endforeach
                    </select>
                    @error('state_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">District</label>
                    <select name="district_id" x-model="districtId" @change="talukaId = ''" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select district —</option>
                        <template x-for="district in visibleDistricts" :key="district.id"><option :value="district.id" x-text="district.name" :selected="district.id === districtId"></option></template>
                    </select>
                    @error('district_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Taluka</label>
                    <select name="taluka_id" x-model="talukaId" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select taluka —</option>
                        <template x-for="taluka in visibleTalukas" :key="taluka.id"><option :value="taluka.id" x-text="taluka.name" :selected="taluka.id === talukaId"></option></template>
                    </select>
                    @error('taluka_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Village</label>
                    <select name="village_id" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select village —</option>
                        <template x-for="village in visibleVillages" :key="village.id"><option :value="village.id" x-text="village.name" :selected="village.id === '{{ old('village_id', $dealer->village_id) }}'"></option></template>
                    </select>
                    @error('village_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="text-sm font-semibold text-slate-700">PIN code</label><input name="pin_code" value="{{ old('pin_code', $dealer->pin_code) }}" maxlength="6" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('pin_code')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Address</label><textarea name="address" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('address', $dealer->address) }}</textarea>@error('address')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Notes</div>
            <div><textarea name="remarks" rows="3" placeholder="Internal remarks (not shown to the dealer)" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks', $dealer->remarks) }}</textarea>@error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('dealers.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $dealer->exists ? 'Save changes' : 'Register dealer' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
