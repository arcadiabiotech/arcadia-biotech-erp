@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto p-6 bg-white rounded shadow">

    <h2 class="text-2xl font-bold mb-6">

        Add Taluka

    </h2>

    <form action="{{ route('talukas.store') }}" method="POST">

        @csrf

        <div class="mb-5">

            <label class="font-semibold">

                District

            </label>

            <select
                name="district_id"
                class="w-full border rounded px-3 py-2 mt-1"
                required>

                <option value="">— Select District —</option>

                @foreach($districts as $district)
                    <option value="{{ $district->id }}" @selected(old('district_id') == $district->id)>{{ $district->name }}</option>
                @endforeach

            </select>

        </div>

        <div class="mb-5">

            <label class="font-semibold">

                Taluka Name

            </label>

            <input
                type="text"
                name="name"
                value="{{ old('name') }}"
                class="w-full border rounded px-3 py-2 mt-1"
                required>

        </div>

        <button
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">

            Save

        </button>

    </form>

</div>

@endsection
