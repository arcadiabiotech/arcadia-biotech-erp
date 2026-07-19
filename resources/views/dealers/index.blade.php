@extends('layouts.app')

@section('content')

<div class="container mx-auto px-6 py-6">

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-5">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex justify-between items-center mb-5">

        <div>
            <h2 class="text-2xl font-bold">
                Dealer Management
            </h2>

            <p class="text-gray-500">
                Total Dealers :
                <strong>{{ $dealers->total() }}</strong>
            </p>
        </div>

        <a href="{{ route('dealers.create') }}"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded font-bold">

            + Add Dealer

        </a>

    </div>

    <form method="GET"
          action="{{ route('dealers.index') }}"
          class="mb-5 flex gap-3">

        <input
            type="text"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search Dealer..."

            class="border rounded px-4 py-2 w-80 text-black">

        <button
            class="bg-green-600 hover:bg-green-700 text-white px-5 rounded">

            Search

        </button>

        <a href="{{ route('dealers.index') }}"
            class="bg-gray-500 text-white px-5 py-2 rounded">

            Reset

        </a>

    </form>

    <table class="w-full border">

        <thead class="bg-gray-200">

            <tr>

                <th class="border p-2">Code</th>

                <th class="border p-2">Dealer</th>

                <th class="border p-2">Firm</th>

                <th class="border p-2">Mobile</th>

                <th class="border p-2">Status</th>

                <th class="border p-2">Action</th>

            </tr>

        </thead>

        <tbody>

            @forelse($dealers as $dealer)

                <tr>

                    <td class="border p-2">

                        {{ $dealer->dealer_code }}

                    </td>

                    <td class="border p-2">

                        {{ $dealer->dealer_name }}

                    </td>

                    <td class="border p-2">

                        {{ $dealer->firm_name }}

                    </td>

                    <td class="border p-2">

                        {{ $dealer->mobile }}

                    </td>

                    <td class="border p-2">

                        @if($dealer->status)

                            <span class="bg-green-600 text-white px-3 py-1 rounded">

                                Active

                            </span>

                        @else

                            <span class="bg-red-600 text-white px-3 py-1 rounded">

                                Inactive

                            </span>

                        @endif

                    </td>

                    <td class="border p-2">

                        <div class="flex gap-2">

                            <a href="{{ route('dealers.edit',$dealer->id) }}"
                                class="bg-yellow-500 text-white px-3 py-1 rounded">

                                Edit

                            </a>

                            <form
                                action="{{ route('dealers.destroy',$dealer->id) }}"
                                method="POST"
                                onsubmit="return confirm('Delete Dealer?')">

                                @csrf
                                @method('DELETE')

                                <button
                                    class="bg-red-600 text-white px-3 py-1 rounded">

                                    Delete

                                </button>

                            </form>

                        </div>

                    </td>

                </tr>

            @empty

                <tr>

                    <td colspan="6"
                        class="text-center p-5">

                        No Dealers Found

                    </td>

                </tr>

            @endforelse

        </tbody>

    </table>

    <div class="mt-5">

        {{ $dealers->links() }}

    </div>

</div>

@endsection