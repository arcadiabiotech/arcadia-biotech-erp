@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto bg-white shadow rounded-lg p-6">

    <div class="flex justify-between items-center mb-6">

        <div>

            <h2 class="text-2xl font-bold text-gray-800">
                Dealer Registration
            </h2>

            <p class="text-gray-500">
                Register New Dealer
            </p>

        </div>

        <a href="{{ route('dealers.index') }}"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">

            Back

        </a>

    </div>

    @if ($errors->any())

        <div class="bg-red-100 border border-red-400 text-red-700 p-3 rounded mb-5">

            <ul class="list-disc ml-5">

                @foreach ($errors->all() as $error)

                    <li>{{ $error }}</li>

                @endforeach

            </ul>

        </div>

    @endif

    <form action="{{ route('dealers.store') }}" method="POST">

        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <div>
                <label class="font-semibold">Firm Name *</label>

                <input type="text"
                    name="firm_name"
                    value="{{ old('firm_name') }}"
                    class="w-full border rounded px-3 py-2 mt-1"
                    required>
            </div>

            <div>

                <label class="font-semibold">Dealer Name *</label>

                <input type="text"
                    name="dealer_name"
                    value="{{ old('dealer_name') }}"
                    class="w-full border rounded px-3 py-2 mt-1"
                    required>

            </div>

            <div>

                <label class="font-semibold">Mobile *</label>

                <input type="text"
                    name="mobile"
                    maxlength="10"
                    value="{{ old('mobile') }}"
                    class="w-full border rounded px-3 py-2 mt-1"
                    required>

            </div>

            <div>

                <label class="font-semibold">WhatsApp</label>

                <input type="text"
                    name="whatsapp"
                    maxlength="10"
                    value="{{ old('whatsapp') }}"
                    class="w-full border rounded px-3 py-2 mt-1">

            </div>

            <div>

                <label class="font-semibold">Email</label>

                <input type="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="w-full border rounded px-3 py-2 mt-1">

            </div>

            <div>

                <label class="font-semibold">GST Number</label>

                <input type="text"
                    name="gst_number"
                    value="{{ old('gst_number') }}"
                    class="w-full border rounded px-3 py-2 mt-1">

            </div>

            <div>

                <label class="font-semibold">PAN Number</label>

                <input type="text"
                    name="pan_number"
                    value="{{ old('pan_number') }}"
                    class="w-full border rounded px-3 py-2 mt-1">

            </div>

            <div class="md:col-span-2">

                <label class="font-semibold">Address</label>

                <textarea
                    name="address"
                    rows="3"
                    class="w-full border rounded px-3 py-2 mt-1">{{ old('address') }}</textarea>

            </div>

        </div>

        <div class="mt-6">

            <button
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-bold">

                Save Dealer

            </button>

        </div>

    </form>

</div>

@endsection