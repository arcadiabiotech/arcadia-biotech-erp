<x-app-layout>
    <div
        class="mx-auto max-w-4xl"
        x-data="{
            farmers: {{ Js::from($farmers->map(fn ($f) => ['id' => (string) $f->id, 'name' => $f->farmer_name, 'dealer_id' => (string) $f->dealer_id])) }},
            dealers: {{ Js::from($dealers->map(fn ($d) => ['id' => (string) $d->id, 'name' => $d->dealer_name])) }},
            dealerId: '{{ old('dealer_id', $booking->dealer_id) }}',
            farmerId: '{{ old('farmer_id', $booking->farmer_id) }}',
            get visibleFarmers() { return this.farmers.filter(f => f.dealer_id === this.dealerId) },
            get quickAddDealerId() { return this.dealerId },
            get quickAddDealerName() {
                const dealer = this.dealers.find(d => d.id === this.dealerId);
                return dealer ? dealer.name : '';
            },
            farmerRegistered(farmer) {
                this.farmers.push({ id: String(farmer.id), name: farmer.farmer_name, dealer_id: String(farmer.dealer_id) });
                this.farmerId = String(farmer.id);
            },
            dealerRegistered(dealer) {
                this.dealers.push({ id: String(dealer.id), name: dealer.dealer_name });
                this.dealerId = String(dealer.id);
            },
        }"
    >
        <div class="mb-8">
            <a href="{{ $booking->exists ? route('bookings.show', $booking) : route('bookings.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $booking->exists ? 'Edit booking' : 'New booking' }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $booking->exists ? $booking->booking_no : 'A booking number is generated automatically on save.' }}</p>
        </div>

        <form
            method="POST" action="{{ $booking->exists ? route('bookings.update', $booking) : route('bookings.store') }}"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($booking->exists) @method('PUT') @endif

            <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Parties</div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Dealer</label>
                    <select name="dealer_id" x-model="dealerId" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select dealer —</option>
                        <template x-for="dealer in dealers" :key="dealer.id"><option :value="dealer.id" x-text="dealer.name"></option></template>
                    </select>
                    @error('dealer_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    <button type="button" @click="$dispatch('open-modal', 'register-dealer')" class="mt-1 text-xs font-semibold text-emerald-600 hover:text-emerald-800">+ Register new dealer</button>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Farmer</label>
                    <select name="farmer_id" x-model="farmerId" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select farmer —</option>
                        <template x-for="farmer in visibleFarmers" :key="farmer.id"><option :value="farmer.id" x-text="farmer.name"></option></template>
                    </select>
                    @error('farmer_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    <button
                        type="button" @click="$dispatch('open-modal', 'register-farmer')" :disabled="! dealerId"
                        :title="! dealerId ? 'Select a dealer first' : ''"
                        class="mt-1 text-xs font-semibold text-emerald-600 hover:text-emerald-800 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:text-slate-300">
                        + Register new farmer under this dealer
                    </button>
                </div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Booking details</div>
            <div class="grid gap-6 sm:grid-cols-3">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Variety</label>
                    <select name="variety" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Select —</option>
                        @foreach($varieties as $variety)<option value="{{ $variety }}" @selected(old('variety', $booking->variety) === $variety)>{{ $variety }}</option>@endforeach
                    </select>
                    @error('variety')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="text-sm font-semibold text-slate-700">Booking date</label><input type="date" name="booking_date" value="{{ old('booking_date', optional($booking->booking_date)->format('Y-m-d')) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('booking_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Plant quantity</label><input type="number" min="1" name="plant_qty" value="{{ old('plant_qty', $booking->plant_qty) }}" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('plant_qty')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">
                Pricing @unless($canChangePricing)<span class="font-normal normal-case text-slate-400">(rate & discount are Admin-only)</span>@endunless
            </div>
            <div class="grid gap-6 sm:grid-cols-3">
                <div><label class="text-sm font-semibold text-slate-700">Plant rate (₹)</label><input type="number" step="0.01" min="0" name="plant_rate" value="{{ old('plant_rate', $booking->plant_rate) }}" required @disabled(! $canChangePricing) class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-50 disabled:text-slate-400">@if(! $canChangePricing)<input type="hidden" name="plant_rate" value="{{ $booking->plant_rate }}">@endif @error('plant_rate')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-sm font-semibold text-slate-700">Discount (₹)</label><input type="number" step="0.01" min="0" name="discount" value="{{ old('discount', $booking->discount) }}" @disabled(! $canChangePricing) class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-50 disabled:text-slate-400">@if(! $canChangePricing)<input type="hidden" name="discount" value="{{ $booking->discount ?? 0 }}">@endif @error('discount')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                @unless($booking->exists)
                    <div><label class="text-sm font-semibold text-slate-700">Advance amount (₹)</label><input type="number" step="0.01" min="0" name="advance_amount" value="{{ old('advance_amount', 0) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">@error('advance_amount')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                @endunless
            </div>

            <div class="mb-2 mt-8 text-xs font-bold uppercase tracking-wide text-slate-400">Notes</div>
            <div><textarea name="remarks" rows="3" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks', $booking->remarks) }}</textarea>@error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ $booking->exists ? route('bookings.show', $booking) : route('bookings.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $booking->exists ? 'Save changes' : 'Create booking' }}</button>
            </div>
        </form>

        <x-quick-register-farmer-modal :states="$states" :districts="$districts" :talukas="$talukas" :villages="$villages" />
        <x-quick-register-dealer-modal />
    </div>
</x-app-layout>
