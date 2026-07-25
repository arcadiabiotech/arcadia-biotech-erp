<x-app-layout>

<x-slot name="header">
    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
        Talukas
    </h2>
</x-slot>

<div class="py-6 px-4 sm:px-6 lg:px-8">

    @if(session('success'))
        <div class="mb-4 rounded bg-green-100 border border-green-400 text-green-700 px-4 py-3">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Talukas</h1>
            <p class="text-gray-500">Manage Taluka Master</p>
        </div>

        <a href="{{ route('talukas.create') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg">
            + Add Taluka
        </a>
    </div>

    <form method="GET" class="mb-6 flex items-center gap-3">

    <input
        type="text"
        name="search"
        value="{{ request('search') }}"
        placeholder="Search Taluka or District..."
        class="w-80 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">

    <button
        type="submit"
        class="min-w-[120px] px-5 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow">

        🔍 Search

    </button>

    <a
        href="{{ route('talukas.index') }}"
        class="min-w-[100px] text-center px-5 py-2 bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded-lg shadow">

        Reset

    </a>

</form>

    <div class="bg-white shadow rounded-lg overflow-hidden">

        <table class="w-full">

            <thead class="bg-gray-100">

                <tr>

                    <th class="p-3 text-left">#</th>

                    <th class="p-3 text-left">Taluka</th>

                    <th class="p-3 text-left">District</th>

                    <th class="p-3 text-center">Status</th>

                    <th class="p-3 text-center">Action</th>

                </tr>

            </thead>

            <tbody>

            @forelse($talukas as $taluka)

                <tr class="border-t">

                    <td class="p-3">
                        {{ $loop->iteration }}
                    </td>

                    <td class="p-3">
                        {{ $taluka->name }}
                    </td>

                    <td class="p-3">
                        {{ $taluka->district->name ?? '—' }}
                    </td>

                    <td class="p-3 text-center">

                        @if($taluka->status)

                            <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full">
                                Active
                            </span>

                        @else

                            <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full">
                                Inactive
                            </span>

                        @endif

                    </td>

                    <td class="p-3">

                        <div class="flex justify-center gap-2">

                            <a href="{{ route('talukas.edit',$taluka) }}"
                               class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded">
                                Edit
                            </a>

                            <form method="POST"
                                  action="{{ route('talukas.destroy',$taluka) }}"
                                  onsubmit="return confirm('Delete this taluka?')">

                                @csrf
                                @method('DELETE')

                                <button
                                    class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded">

                                    Delete

                                </button>

                            </form>

                        </div>

                    </td>

                </tr>

            @empty

                <tr>

                    <td colspan="5"
                        class="text-center p-6 text-gray-500">

                        No Taluka Found.

                    </td>

                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    <div class="mt-5">

        {{ $talukas->links() }}

    </div>

</div>

</x-app-layout>
