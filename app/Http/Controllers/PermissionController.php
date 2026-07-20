<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $permissions = Permission::withCount('roles')->when($request->string('search')->toString(), fn ($query, $search) => $query->where(fn ($q) => $q->where('display_name', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('group', 'like', "%{$search}%")))->orderBy('group')->orderBy('display_name')->paginate(10)->withQueryString();

        return view('permissions.index', compact('permissions'));
    }

    public function create()
    {
        return view('permissions.form', ['permission' => new Permission()]);
    }

    public function store(Request $request)
    {
        Permission::create($this->validated($request));

        return to_route('permissions.index')->with('success', 'Permission created successfully.');
    }

    public function edit(Permission $permission)
    {
        return view('permissions.form', compact('permission'));
    }

    public function update(Request $request, Permission $permission)
    {
        $permission->update($this->validated($request, $permission));

        return to_route('permissions.index')->with('success', 'Permission updated successfully.');
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();

        return to_route('permissions.index')->with('success', 'Permission deleted successfully.');
    }

    private function validated(Request $request, ?Permission $permission = null): array
    {
        $data = $request->validate(['display_name' => ['required', 'string', 'max:150'], 'group' => ['nullable', 'string', 'max:100'], 'status' => ['required', 'boolean']]);
        $data['name'] = Str::slug($data['display_name'], '.');
        validator($data, ['name' => [Rule::unique('permissions', 'name')->ignore($permission?->id)]])->validate();

        return $data;
    }
}
