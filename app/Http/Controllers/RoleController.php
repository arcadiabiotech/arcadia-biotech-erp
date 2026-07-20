<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where(fn ($q) => $q->where('display_name', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('display_name')->paginate(10)->withQueryString();

        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        return view('roles.form', ['role' => new Role(), 'permissions' => Permission::active()->orderBy('group')->orderBy('display_name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = Role::create($data);
        $role->permissions()->sync($request->input('permissions', []));

        return to_route('roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        return view('roles.form', ['role' => $role->load('permissions'), 'permissions' => Permission::active()->orderBy('group')->orderBy('display_name')->get()]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validated($request, $role);
        $role->update($data);
        $role->permissions()->sync($request->input('permissions', []));

        return to_route('roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->name === 'super-admin', 422, 'The Super Admin role cannot be deleted.');
        abort_if($role->users()->exists(), 422, 'Roles assigned to users cannot be deleted.');
        $role->delete();

        return to_route('roles.index')->with('success', 'Role deleted successfully.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate(['display_name' => ['required', 'string', 'max:100'], 'status' => ['required', 'boolean'], 'permissions' => ['nullable', 'array'], 'permissions.*' => ['integer', Rule::exists('permissions', 'id')]]);
        $data['name'] = Str::slug($data['display_name']);
        $data['name'] = $role?->name === 'super-admin' ? 'super-admin' : $data['name'];
        validator($data, ['name' => [Rule::unique('roles', 'name')->ignore($role?->id)]])->validate();

        return $data;
    }
}
