@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto p-6 bg-white rounded shadow">

    <h2 class="text-2xl font-bold mb-6">

        Edit Village

    </h2>

    <form
        action="{{ route('villages.update',$village) }}"
        method="POST">

        @csrf
        @method('PUT')

        <div class="mb-5">

            <label class="font-semibold">

                Taluka

            </label>

            <select
                name="taluka_id"
                class="w-full border rounded px-3 py-2 mt-1"
                required>

                @foreach($talukas as $taluka)
                    <option value="{{ $taluka->id }}" @selected($village->taluka_id == $taluka->id)>{{ $taluka->name }}</option>
                @endforeach

            </select>

        </div>

        <div class="mb-5">

            <label class="font-semibold">

                Village Name

            </label>

            <input
                type="text"
                name="name"
                value="{{ $village->name }}"
                class="w-full border rounded px-3 py-2 mt-1"
                required>

        </div>

        <div class="mb-6">

            <label>

                <input
                    type="checkbox"
                    name="status"
                    {{ $village->status ? 'checked' : '' }}>

                Active

            </label>

        </div>

        <button
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">

            Update

        </button>

    </form>

</div>

@endsection
