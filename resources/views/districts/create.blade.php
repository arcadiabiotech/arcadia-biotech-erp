@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto p-6 bg-white rounded shadow">

    <h2 class="text-2xl font-bold mb-6">

        Add District

    </h2>

    <form action="{{ route('districts.store') }}" method="POST">

        @csrf

        <div class="mb-5">

            <label class="font-semibold">

                State

            </label>

            <select
                name="state_id"
                class="w-full border rounded px-3 py-2 mt-1"
                required>

                <option value="">— Select State —</option>

                @foreach($states as $state)
                    <option value="{{ $state->id }}" @selected(old('state_id') == $state->id)>{{ $state->name }}</option>
                @endforeach

            </select>

        </div>

        <div class="mb-5">

            <label class="font-semibold">

                District Name

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
