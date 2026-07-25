<x-app-layout>
    <div class="mx-auto max-w-5xl" x-data="{ tab: 'details', cancelling: false, emailing: false, returning: false, delivering: false, vehicleReturning: false }">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('dispatches.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to dispatches</a>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $dispatch->dispatch_no }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $dispatch->dealers->pluck('dealer_name')->implode(', ') }} · {{ $dispatch->lines->count() }} booking{{ $dispatch->lines->count() === 1 ? '' : 's' }} · {{ number_format($dispatch->total_qty) }} plants</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-dispatch-status-badge :status="$dispatch->status" class="!px-3 !py-1.5" />
                @if($dispatch->vehicle_status_label)
                    <span class="inline-flex rounded-full bg-sky-100 px-3 py-1.5 text-sm font-semibold text-sky-700">Vehicle: {{ $dispatch->vehicle_status_label }}</span>
                @endif
                <a href="{{ route('dispatches.print', $dispatch) }}" target="_blank" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Print challan</a>
                <a href="{{ route('dispatches.pdf', $dispatch) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Download PDF</a>
                @if($dispatch->challan_no)
                    @php
                        $firstDealer = $dispatch->lines->first()?->dealer;
                        $firstFarmer = $dispatch->lines->first()?->farmer;
                        $challanShareUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('challans.public', now()->addDays(60), ['dispatch' => $dispatch->id]);
                        $whatsappNumber = preg_replace('/\D/', '', $firstDealer?->whatsapp ?: $firstDealer?->mobile ?: $firstFarmer?->mobile ?: '');
                        $whatsappMessage = "Delivery Challan {$dispatch->challan_no} — {$challanShareUrl}";
                    @endphp
                    <a href="https://wa.me/{{ $whatsappNumber }}?text={{ urlencode($whatsappMessage) }}" target="_blank" rel="noopener" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-100">Share WhatsApp</a>
                    <button type="button" @click="emailing = ! emailing" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Email PDF</button>
                @endif
                @can('update', $dispatch)<a href="{{ route('dispatches.edit', $dispatch) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Edit</a>@endcan
            </div>
        </div>

        @if($dispatch->challan_no)
            <div x-show="emailing" x-cloak class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <form method="POST" action="{{ route('dispatches.email-challan', $dispatch) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex-1"><label class="text-xs font-semibold text-slate-600">Send challan PDF to email</label><input type="email" name="email" required value="{{ old('email', $dispatch->lines->first()?->dealer?->email) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></div>
                    <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Send</button>
                </form>
            </div>
        @endif

        @if (session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="mb-6 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            @can('submit', $dispatch)
                <form method="POST" action="{{ route('dispatches.submit', $dispatch) }}">@csrf<button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Submit dispatch</button></form>
            @endcan
            @can('startLoading', $dispatch)
                <form method="POST" action="{{ route('dispatches.start-loading', $dispatch) }}" onsubmit="return confirm('Dispatch this vehicle? This will automatically generate the challan.')">@csrf<button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">🚚 Dispatch Vehicle</button></form>
            @endcan
            @can('vehicleOut', $dispatch)
                <form method="POST" action="{{ route('dispatches.vehicle-out', $dispatch) }}" onsubmit="return confirm('Confirm the loaded vehicle has physically left the nursery? No further changes are allowed after this.')">@csrf<button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">🚛 Vehicle Left Nursery</button></form>
            @endcan
            @can('markDelivered', $dispatch)
                <button type="button" @click="delivering = ! delivering" class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">✅ Delivery Completed</button>
            @endcan
            @can('complete', $dispatch)
                <form method="POST" action="{{ route('dispatches.complete', $dispatch) }}" onsubmit="return confirm('Complete this dispatch? This converts the stock reservation into an actual stock issue.')">@csrf<button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Complete dispatch</button></form>
            @endcan
            @can('markVehicleReturned', $dispatch)
                <button type="button" @click="vehicleReturning = ! vehicleReturning" class="inline-flex min-w-fit items-center gap-2 whitespace-nowrap rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">
                    <span>{{ $dispatch->odometer_end === null ? '↩' : '✔' }}</span>
                    <span>{{ $dispatch->odometer_end === null ? 'Vehicle Return' : 'Vehicle Returned' }}</span>
                </button>
            @endcan
            @can('recordReturn', $dispatch)
                @if($dispatch->vehicle_returned_by)
                    <button type="button" @click="returning = ! returning" class="rounded-xl border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50">Return Inspection</button>
                @endif
            @endcan
            @can('cancel', $dispatch)
                <button type="button" @click="cancelling = ! cancelling" class="rounded-xl border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Cancel dispatch</button>
            @endcan
            @can('unlock', $dispatch)
                <form method="POST" action="{{ route('dispatches.unlock', $dispatch) }}">@csrf<button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unlock</button></form>
            @endcan
            @can('delete', $dispatch)
                <form method="POST" action="{{ route('dispatches.destroy', $dispatch) }}" class="ml-auto" onsubmit="return confirm('Delete this dispatch?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 hover:text-rose-800">Delete dispatch</button></form>
            @endcan
        </div>

        @can('cancel', $dispatch)
            <div x-show="cancelling" x-cloak class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4">
                <form method="POST" action="{{ route('dispatches.cancel', $dispatch) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex-1"><label class="text-xs font-semibold text-slate-600">Reason for cancellation (required)</label><input type="text" name="reason" required minlength="5" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500"></div>
                    <button class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Confirm cancel</button>
                </form>
            </div>
        @endcan

        @can('markVehicleReturned', $dispatch)
            <div x-show="vehicleReturning" x-cloak class="mb-6 rounded-2xl border border-cyan-200 bg-cyan-50 p-4">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-cyan-700">Vehicle Returned — Transport Cost</h2>
                <p class="mb-3 text-xs text-slate-500">Odometer End is mandatory. Total KM and Transport Cost are calculated automatically from the vehicle's Cost Per KM.</p>
                <form method="POST" action="{{ route('dispatches.vehicle-returned', $dispatch) }}" class="flex flex-wrap items-end gap-3" onsubmit="return confirm('Confirm the vehicle has physically returned to the nursery?')">
                    @csrf
                    <div>
                        <label class="text-xs font-semibold text-slate-600">Odometer Start</label>
                        <p class="mt-1 flex h-[38px] w-32 items-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-700">{{ $dispatch->odometer_start ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600">Odometer End <span class="text-rose-500">*</span></label>
                        <input type="number" min="0" required name="odometer_end" value="{{ old('odometer_end', $dispatch->odometer_end) }}" class="mt-1 block w-32 rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600">Toll Charges <span class="font-normal text-slate-400">(optional)</span></label>
                        <input type="number" step="0.01" min="0" name="toll_charges" value="{{ old('toll_charges', $dispatch->toll_charges) }}" class="mt-1 block w-32 rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600">Other Expenses <span class="font-normal text-slate-400">(optional)</span></label>
                        <input type="number" step="0.01" min="0" name="other_expenses" value="{{ old('other_expenses', $dispatch->other_expenses) }}" class="mt-1 block w-32 rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600">Driver Allowance <span class="font-normal text-slate-400">(optional)</span></label>
                        <input type="number" step="0.01" min="0" name="driver_allowance" value="{{ old('driver_allowance', $dispatch->driver_allowance) }}" class="mt-1 block w-32 rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <button class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Save</button>
                </form>
            </div>
        @endcan

        @can('markDelivered', $dispatch)
            <div x-show="delivering" x-cloak class="mb-6 rounded-2xl border border-teal-200 bg-teal-50 p-4">
                <p class="mb-3 text-xs text-slate-500">Leave "Rejected by farmer" at 0 for a normal, fully-accepted delivery — only fill it in if the farmer refused some plants.</p>
                <form method="POST" action="{{ route('dispatches.deliver', $dispatch) }}" class="space-y-3">
                    @csrf
                    @foreach($dispatch->lines as $line)
                        <div class="flex flex-wrap items-end gap-3" x-data="{ rejected: 0, total: {{ $line->total_qty }} }">
                            <div class="min-w-0 flex-1 text-xs text-slate-600">
                                <p class="font-semibold text-slate-800">{{ $line->booking->booking_no }} — {{ $line->dealer?->dealer_name }}</p>
                                <p>{{ number_format($line->total_qty) }} plants shipped</p>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Rejected by farmer</label>
                                <input type="number" min="0" :max="total" x-model.number="rejected" name="lines[{{ $line->id }}][rejected_qty]" class="mt-1 block w-28 rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            </div>
                            <div class="text-xs text-slate-600">Accepted: <span class="font-semibold text-slate-800" x-text="total - rejected"></span></div>
                            <input type="hidden" name="lines[{{ $line->id }}][accepted_qty]" :value="total - rejected">
                            <div class="min-w-[10rem] flex-1" x-show="rejected > 0" x-cloak>
                                <label class="text-xs font-semibold text-slate-600">Reason</label>
                                <input type="text" name="lines[{{ $line->id }}][rejection_reason]" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" placeholder="e.g. damaged in transit">
                            </div>
                        </div>
                    @endforeach
                    <div class="grid gap-3 border-t border-teal-200 pt-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-slate-600">Delivery location</label>
                            <input type="text" name="delivery_location" value="{{ old('delivery_location') }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600">Receiver name</label>
                            <input type="text" name="receiver_name" value="{{ old('receiver_name') }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600">Receiver mobile</label>
                            <input type="text" name="receiver_mobile" maxlength="15" value="{{ old('receiver_mobile') }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600">Receiver remarks <span class="font-normal text-slate-400">(optional)</span></label>
                            <input type="text" name="receiver_remarks" value="{{ old('receiver_remarks') }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Confirm delivery</button>
                    </div>
                </form>
            </div>
        @endcan

        @can('recordReturn', $dispatch)
            @if($dispatch->vehicle_returned_by)
                <div x-show="returning" x-cloak class="mb-6 rounded-2xl border border-teal-200 bg-teal-50 p-4">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-teal-700">Return Inspection</h2>
                    <p class="mb-3 text-xs text-slate-500">Vehicle {{ $dispatch->vehicle?->vehicle_no }} — Driver {{ $dispatch->driver_name ?? '—' }}</p>
                    <form method="POST" action="{{ route('dispatches.record-return', $dispatch) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        @foreach($dispatch->lines as $line)
                            @if($line->crate_count !== null)
                                <div class="rounded-xl border border-slate-200 bg-white p-3">
                                    <p class="text-xs font-semibold text-slate-800">{{ $line->booking->booking_no }} — {{ $line->dealer?->dealer_name }}</p>
                                    <div class="mt-2 flex flex-wrap items-end gap-3">
                                        <div>
                                            <label class="text-xs font-semibold text-slate-600">Loaded crates</label>
                                            <p class="mt-1 flex h-[38px] w-28 items-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-700">{{ rtrim(rtrim(number_format($line->crate_count, 2), '0'), '.') }}</p>
                                        </div>
                                        <div>
                                            <label class="text-xs font-semibold text-slate-600">Returned crates <span class="text-rose-500">*</span></label>
                                            <input type="number" step="0.01" min="0" required name="lines[{{ $line->id }}][returned_qty]" value="{{ old("lines.{$line->id}.returned_qty", (float) ($line->crates_returned ?? 0)) }}" class="mt-1 block w-28 rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                        </div>
                                        <div>
                                            <label class="text-xs font-semibold text-slate-600">Missing crates</label>
                                            <input type="number" step="0.01" min="0" name="lines[{{ $line->id }}][missing_qty]" value="{{ old("lines.{$line->id}.missing_qty", (float) ($line->missing_qty ?? 0)) }}" class="mt-1 block w-28 rounded-xl border-slate-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500">
                                        </div>
                                        <div>
                                            <label class="text-xs font-semibold text-slate-600">Broken crates</label>
                                            <input type="number" step="0.01" min="0" name="lines[{{ $line->id }}][broken_qty]" value="{{ old("lines.{$line->id}.broken_qty", (float) ($line->broken_qty ?? 0)) }}" class="mt-1 block w-28 rounded-xl border-slate-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500">
                                        </div>
                                        <div>
                                            <label class="text-xs font-semibold text-slate-600">Damaged crates</label>
                                            <input type="number" step="0.01" min="0" name="lines[{{ $line->id }}][damage_qty]" value="{{ old("lines.{$line->id}.damage_qty", (float) ($line->damage_qty ?? 0)) }}" class="mt-1 block w-28 rounded-xl border-slate-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500">
                                        </div>
                                        <div>
                                            <label class="text-xs font-semibold text-slate-600">Returned plants</label>
                                            <p class="mt-1 flex h-[38px] w-28 items-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-700">{{ number_format($line->rejected_qty ?? 0) }}</p>
                                        </div>
                                        <div>
                                            <label class="text-xs font-semibold text-slate-600">Dead plants</label>
                                            <input type="number" min="0" name="lines[{{ $line->id }}][dead_plant_qty]" value="{{ old("lines.{$line->id}.dead_plant_qty", (int) ($line->dead_plant_qty ?? 0)) }}" class="mt-1 block w-28 rounded-xl border-slate-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500">
                                        </div>
                                        <div>
                                            <label class="text-xs font-semibold text-slate-600">Extra plants</label>
                                            <input type="number" min="0" name="lines[{{ $line->id }}][extra_returned_qty]" value="{{ old("lines.{$line->id}.extra_returned_qty", (int) ($line->extra_returned_qty ?? 0)) }}" class="mt-1 block w-28 rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Return date</label>
                                <input type="date" name="vehicle_returned_at" required value="{{ old('vehicle_returned_at', $dispatch->vehicle_returned_at?->toDateString() ?? now()->toDateString()) }}" class="mt-1 block rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            </div>
                            <div class="flex-1">
                                <label class="text-xs font-semibold text-slate-600">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
                                <input type="text" name="return_remarks" value="{{ old('return_remarks', $dispatch->return_remarks) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Damage remarks</label>
                                <textarea name="damage_remarks" rows="2" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">{{ old('damage_remarks', $dispatch->damage_remarks) }}</textarea>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Driver remarks</label>
                                <textarea name="driver_remarks" rows="2" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">{{ old('driver_remarks', $dispatch->driver_remarks) }}</textarea>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Supervisor remarks</label>
                                <textarea name="supervisor_remarks" rows="2" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">{{ old('supervisor_remarks', $dispatch->supervisor_remarks) }}</textarea>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600">Photos <span class="font-normal text-slate-400">(optional)</span></label>
                            <input type="file" name="return_photos[]" multiple accept="image/*" class="mt-1 block w-full text-sm text-slate-600">
                        </div>
                        <div class="flex justify-end">
                            <button class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Save return inspection</button>
                        </div>
                    </form>
                </div>
            @endif
        @endcan

        <div class="mb-6 flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            @foreach(['details' => 'Details', 'documents' => 'Documents', 'timeline' => 'Timeline', 'history' => 'Audit history'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-lg px-4 py-2 text-sm font-semibold transition">{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'details'" class="grid gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Bookings on this dispatch</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="py-2 pr-3 text-left">Booking</th>
                                <th class="py-2 pr-3 text-left">Dealer / Farmer</th>
                                <th class="py-2 pr-3 text-right">Qty</th>
                                <th class="py-2 pr-3 text-right">Extra</th>
                                <th class="py-2 pr-3 text-right">Total</th>
                                <th class="py-2 pr-3 text-left">Delivery</th>
                                <th class="py-2 pr-3 text-left">Batch / Age</th>
                                <th class="py-2 pr-3 text-left">Crates</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($dispatch->lines as $line)
                                <tr>
                                    <td class="py-2 pr-3 font-medium text-slate-800">{{ $line->booking->booking_no }}</td>
                                    <td class="py-2 pr-3 text-slate-600">{{ $line->dealer?->dealer_name }} / {{ $line->farmer?->farmer_name }}</td>
                                    <td class="py-2 pr-3 text-right text-slate-600">{{ number_format($line->dispatch_qty) }}</td>
                                    <td class="py-2 pr-3 text-right text-slate-600">{{ number_format($line->extra_qty) }}</td>
                                    <td class="py-2 pr-3 text-right font-semibold text-slate-800">{{ number_format($line->total_qty) }}</td>
                                    <td class="py-2 pr-3 text-slate-600">
                                        @if(! $line->delivery_recorded)
                                            —
                                        @elseif($line->rejected_qty > 0)
                                            <span class="inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">{{ number_format($line->rejected_qty) }} rejected</span>
                                            @if($line->rejection_reason)<p class="mt-0.5 text-xs text-slate-400">{{ $line->rejection_reason }}</p>@endif
                                        @else
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Fully accepted</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-3 text-slate-600">{{ collect([$line->batch_number, $line->plant_age])->filter()->implode(' · ') ?: '—' }}</td>
                                    <td class="py-2 pr-3 text-slate-600">
                                        @if($line->crate_count === null)
                                            —
                                        @else
                                            {{ $line->crates_returned !== null ? rtrim(rtrim(number_format((float) $line->crates_returned, 2), '0'), '.') : 0 }} / {{ rtrim(rtrim(number_format($line->crate_count, 2), '0'), '.') }}
                                            @if($line->return_status)
                                                <span @class([
                                                    'ml-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                                    'bg-emerald-100 text-emerald-700' => $line->return_status === 'complete',
                                                    'bg-amber-100 text-amber-700' => $line->return_status === 'partial',
                                                    'bg-slate-100 text-slate-600' => $line->return_status === 'pending',
                                                ])>{{ ucfirst($line->return_status) }}</span>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Shipment</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Dispatch date</dt><dd class="font-medium text-slate-800">{{ $dispatch->dispatch_date?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Expected delivery</dt><dd class="font-medium text-slate-800">{{ $dispatch->expected_delivery_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Actual delivery</dt><dd class="font-medium text-slate-800">{{ $dispatch->delivered_at?->format('d M Y, h:i A') ?? $dispatch->actual_delivery_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Total plants</dt><dd class="font-medium text-slate-800">{{ number_format($dispatch->total_qty) }}</dd></div>
                    @if($dispatch->delivered_at)
                        <div class="flex justify-between"><dt class="text-slate-500">Delivery location</dt><dd class="font-medium text-slate-800">{{ $dispatch->delivery_location ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Received by</dt><dd class="font-medium text-slate-800">{{ $dispatch->receiver_name ?? '—' }}{{ $dispatch->receiver_mobile ? ' · '.$dispatch->receiver_mobile : '' }}</dd></div>
                        @if($dispatch->receiver_remarks)
                            <div class="flex justify-between"><dt class="text-slate-500">Receiver remarks</dt><dd class="font-medium text-slate-800">{{ $dispatch->receiver_remarks }}</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-slate-500">Delivered by</dt><dd class="font-medium text-slate-800">{{ $dispatch->deliveredBy?->name ?? '—' }}</dd></div>
                    @endif
                </dl>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Vehicle &amp; driver</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Vehicle</dt><dd class="font-medium text-slate-800">{{ $dispatch->vehicle?->vehicle_no ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Transport company</dt><dd class="font-medium text-slate-800">{{ $dispatch->vehicle?->transport_company ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Driver</dt><dd class="font-medium text-slate-800">{{ $dispatch->driver_name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Driver mobile</dt><dd class="font-medium text-slate-800">{{ $dispatch->driver_mobile ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">LR number</dt><dd class="font-medium text-slate-800">{{ $dispatch->lr_number ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">GPS location</dt><dd class="font-medium text-slate-800">{{ $dispatch->gps_location ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Vehicle returned</dt><dd class="font-medium text-slate-800">{{ $dispatch->vehicle_returned_at?->format('d M Y') ?? '—' }}</dd></div>
                    @if($dispatch->vehicle_returned_by)
                        <div class="flex justify-between"><dt class="text-slate-500">Returned by</dt><dd class="font-medium text-slate-800">{{ $dispatch->vehicleReturnedBy?->name ?? '—' }}</dd></div>
                    @endif
                    @if($dispatch->total_km !== null)
                        <div class="flex justify-between"><dt class="text-slate-500">Odometer Start / End</dt><dd class="font-medium text-slate-800">{{ $dispatch->odometer_start }} / {{ $dispatch->odometer_end }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Total KM</dt><dd class="font-medium text-slate-800">{{ number_format($dispatch->total_km) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Transport Cost</dt><dd class="font-medium text-slate-800">₹{{ number_format($dispatch->transport_cost, 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Driver Allowance</dt><dd class="font-medium text-slate-800">₹{{ number_format($dispatch->driver_allowance, 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Toll / Other</dt><dd class="font-medium text-slate-800">₹{{ number_format($dispatch->toll_charges, 2) }} / ₹{{ number_format($dispatch->other_expenses, 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Total Transport Expense</dt><dd class="font-bold text-slate-900">₹{{ number_format($dispatch->total_transport_expense, 2) }}</dd></div>
                    @endif
                    @if($dispatch->return_remarks)
                        <div class="flex justify-between"><dt class="text-slate-500">Return remarks</dt><dd class="font-medium text-slate-800">{{ $dispatch->return_remarks }}</dd></div>
                    @endif
                    @if($dispatch->damage_remarks)
                        <div class="flex justify-between"><dt class="text-slate-500">Damage remarks</dt><dd class="font-medium text-slate-800">{{ $dispatch->damage_remarks }}</dd></div>
                    @endif
                    @if($dispatch->driver_remarks)
                        <div class="flex justify-between"><dt class="text-slate-500">Driver remarks</dt><dd class="font-medium text-slate-800">{{ $dispatch->driver_remarks }}</dd></div>
                    @endif
                    @if($dispatch->supervisor_remarks)
                        <div class="flex justify-between"><dt class="text-slate-500">Supervisor remarks</dt><dd class="font-medium text-slate-800">{{ $dispatch->supervisor_remarks }}</dd></div>
                    @endif
                    @if($dispatch->return_photos)
                        <div class="flex justify-between gap-2"><dt class="text-slate-500">Return photos</dt><dd class="flex flex-wrap justify-end gap-1.5">
                            @foreach($dispatch->return_photos as $photo)
                                <a href="{{ Illuminate\Support\Facades\Storage::disk('public')->url($photo) }}" target="_blank" class="text-xs font-semibold text-blue-600 hover:underline">Photo {{ $loop->iteration }}</a>
                            @endforeach
                        </dd></div>
                    @endif
                </dl>
            </div>
            @if($dispatch->total_crates !== null)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Crate return</h2>
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-emerald-100 text-emerald-700' => $dispatch->crate_return_status === 'returned',
                            'bg-amber-100 text-amber-700' => $dispatch->crate_return_status === 'pending',
                        ])>{{ $dispatch->crate_return_status === 'returned' ? 'Returned' : 'Pending' }}</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div>
                            <dt class="text-xs text-slate-500">Total crates</dt>
                            <dd class="mt-1 text-lg font-bold text-slate-800">{{ rtrim(rtrim(number_format($dispatch->total_crates, 2), '0'), '.') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Returned</dt>
                            <dd class="mt-1 inline-flex rounded-lg bg-emerald-50 px-2.5 py-1 text-lg font-bold text-emerald-700">{{ rtrim(rtrim(number_format($dispatch->returned_crates, 2), '0'), '.') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Damage</dt>
                            <dd class="mt-1 inline-flex rounded-lg bg-rose-50 px-2.5 py-1 text-lg font-bold text-rose-700">{{ rtrim(rtrim(number_format($dispatch->damage_crates, 2), '0'), '.') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Pending</dt>
                            <dd class="mt-1 inline-flex rounded-lg bg-amber-50 px-2.5 py-1 text-lg font-bold text-amber-700">{{ rtrim(rtrim(number_format($dispatch->crate_return_pending, 2), '0'), '.') }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Documents reference</h2>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Status</dt><dd class="mt-1"><x-dispatch-status-badge :status="$dispatch->status" /></dd></div>
                    <div><dt class="text-xs text-slate-500">Challan no</dt><dd class="mt-1 font-medium text-slate-800">{{ $dispatch->challan_no ?? '—' }}</dd></div>
                </dl>
            </div>
            @if($dispatch->remarks)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-slate-400">Remarks</h2>
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $dispatch->remarks }}</p>
                </div>
            @endif
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Record</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between"><dt class="text-slate-500">Created by</dt><dd class="font-medium text-slate-800">{{ $dispatch->createdBy?->name ?? '—' }} · {{ $dispatch->created_at?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Last updated by</dt><dd class="font-medium text-slate-800">{{ $dispatch->updatedBy?->name ?? '—' }} · {{ $dispatch->updated_at?->format('d M Y') }}</dd></div>
                </dl>
            </div>
        </div>

        <div x-show="tab === 'documents'" class="grid gap-6 sm:grid-cols-2">
            @if($dealerChallans->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Dealer &amp; Farmer Challans</h2>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('dispatches.challans.print-all', $dispatch) }}" target="_blank" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Print All</a>
                            <a href="{{ route('dispatches.challans.farmers-print', $dispatch) }}" target="_blank" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Print Farmer Challans</a>
                            <a href="{{ route('dispatches.challans.farmers-pdf', $dispatch) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Download All Farmer PDFs</a>
                            <a href="{{ route('dispatches.challans.zip', $dispatch) }}" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700">Download ZIP</a>
                        </div>
                    </div>

                    @foreach($dealerChallans as $dealerChallan)
                        <div class="mb-4 rounded-xl border border-slate-200 p-4 last:mb-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-800">{{ $dealerChallan->challan_no }} <span class="font-normal text-slate-500">— {{ $dealerChallan->dealer->dealer_name }} (Master)</span></p>
                                <div class="flex flex-wrap items-center gap-3">
                                    <a href="{{ route('challan-docs.show', $dealerChallan) }}" target="_blank" class="text-xs font-semibold text-blue-600 hover:text-blue-800">Print Dealer Challan</a>
                                    <a href="{{ route('challan-docs.pdf', $dealerChallan) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">Download Dealer PDF</a>
                                    @php
                                        $dealerWaNumber = preg_replace('/\D/', '', $dealerChallan->dealer->whatsapp ?: $dealerChallan->dealer->mobile ?: '');
                                        $dealerWaUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('challan-docs.public', now()->addDays(60), ['challan' => $dealerChallan->id]);
                                    @endphp
                                    @if($dealerWaNumber)
                                        <a href="https://wa.me/{{ $dealerWaNumber }}?text={{ urlencode("Dealer Challan {$dealerChallan->challan_no} — {$dealerWaUrl}") }}" target="_blank" rel="noopener" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">WhatsApp Dealer</a>
                                    @endif
                                </div>
                            </div>

                            @php
                                $thisDealersFarmerChallans = $farmerChallans->where('parent_challan_id', $dealerChallan->id);
                            @endphp
                            @if($thisDealersFarmerChallans->isNotEmpty())
                                <div class="mt-3 divide-y divide-slate-100 border-t border-slate-100 pt-2">
                                    @foreach($thisDealersFarmerChallans as $farmerChallan)
                                        <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-xs">
                                            <p class="text-slate-700">{{ $farmerChallan->challan_no }} — {{ $farmerChallan->farmer->farmer_name }}</p>
                                            <div class="flex flex-wrap items-center gap-3">
                                                <a href="{{ route('challan-docs.show', $farmerChallan) }}" target="_blank" class="font-semibold text-blue-600 hover:text-blue-800">Print</a>
                                                <a href="{{ route('challan-docs.pdf', $farmerChallan) }}" class="font-semibold text-blue-600 hover:text-blue-800">Download PDF</a>
                                                @php
                                                    $farmerWaNumber = preg_replace('/\D/', '', $farmerChallan->farmer->mobile ?: '');
                                                    $farmerWaUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('challan-docs.public', now()->addDays(60), ['challan' => $farmerChallan->id]);
                                                @endphp
                                                @if($farmerWaNumber)
                                                    {{-- Scoped to this farmer's own challan URL + mobile only — never another farmer's document. --}}
                                                    <a href="https://wa.me/{{ $farmerWaNumber }}?text={{ urlencode("Your Delivery Challan {$farmerChallan->challan_no} — {$farmerWaUrl}") }}" target="_blank" rel="noopener" class="font-semibold text-emerald-700 hover:text-emerald-800">WhatsApp Farmer</a>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Dispatch photo</h2>
                @if($dispatch->photo)
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($dispatch->photo) }}" target="_blank"><img src="{{ \Illuminate\Support\Facades\Storage::url($dispatch->photo) }}" class="max-h-72 rounded-xl border border-slate-200 object-cover"></a>
                @else
                    <p class="text-sm text-slate-500">No photo uploaded.</p>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Delivery signature</h2>
                @if($dispatch->signature)
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($dispatch->signature) }}" target="_blank"><img src="{{ \Illuminate\Support\Facades\Storage::url($dispatch->signature) }}" class="max-h-72 rounded-xl border border-slate-200 object-cover"></a>
                @else
                    <p class="text-sm text-slate-500">No signature captured.</p>
                @endif
            </div>
        </div>

        <div x-show="tab === 'timeline'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @forelse($activity as $entry)
                <div class="relative pb-8 pl-8 last:pb-0">
                    <span class="absolute left-0 top-1 grid h-5 w-5 place-items-center rounded-full bg-blue-600"><span class="h-2 w-2 rounded-full bg-white"></span></span>
                    @unless($loop->last)<span class="absolute left-[9px] top-6 h-full w-px bg-slate-200"></span>@endunless
                    <p class="text-sm font-semibold capitalize text-slate-800">Dispatch {{ $entry->action }}{{ $entry->remarks ? ' — '.$entry->remarks : '' }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $entry->created_at->format('d M Y, h:i A') }} · by {{ $entry->user?->name ?? 'System' }}</p>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-slate-500">No timeline events yet.</p>
            @endforelse
        </div>

        <div x-show="tab === 'history'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse($activity as $entry)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold capitalize text-slate-800">{{ $entry->action }}</span>
                            <span class="text-xs text-slate-400">{{ $entry->created_at->format('d M Y, h:i A') }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">by {{ $entry->user?->name ?? 'System' }}@if($entry->remarks) · {{ $entry->remarks }} @endif</p>
                        @if($entry->old_values && $entry->new_values)
                            <div class="mt-2 space-y-1 rounded-lg bg-slate-50 p-3 text-xs">
                                @foreach(array_intersect_key($entry->new_values, $entry->old_values) as $field => $newValue)
                                    @if(($entry->old_values[$field] ?? null) != $newValue)
                                        <p><span class="font-semibold text-slate-600">{{ $field }}:</span> <span class="text-rose-600 line-through">{{ is_array($entry->old_values[$field] ?? null) ? '—' : ($entry->old_values[$field] ?? '—') }}</span> → <span class="text-emerald-700">{{ is_array($newValue) ? '—' : ($newValue ?? '—') }}</span></p>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="px-6 py-12 text-center text-sm text-slate-500">No activity recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
