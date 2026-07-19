@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto p-6 bg-white rounded shadow">

    <h2 class="text-2xl font-bold mb-6">

        Add State

    </h2>

    <form action="{{ route('states.store') }}" method="POST">

        @csrf

        <div class="mb-5">

            <label class="font-semibold">

                State Name

            </label>

            <input
                type="text"
                name="name"
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
                class="w-full border rounded px-3 py-2 mt-1">

        </div>

        <button
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">

            Save

        </button>

    </form>

</div>

@endsection