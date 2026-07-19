@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto p-6 bg-white rounded shadow">

    <h2 class="text-2xl font-bold mb-6">

        Edit State

    </h2>

    <form
        action="{{ route('states.update',$state) }}"
        method="POST">

        @csrf
        @method('PUT')

        <div class="mb-5">

            <label class="font-semibold">

                State Name

            </label>

            <input
                type="text"
                name="name"
                value="{{ $state->name }}"
                class="w-full border rounded px-3 py-2 mt-1"
                required>

        </div>

        <div class="mb-5">

            <label class="font-semibold">

                State Code

            </label>

            <input
                type="text"
                name="code"
                value="{{ $state->code }}"
                class="w-full border rounded px-3 py-2 mt-1">

        </div>

        <div class="mb-6">

            <label>

                <input
                    type="checkbox"
                    name="status"
                    {{ $state->status ? 'checked' : '' }}>

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