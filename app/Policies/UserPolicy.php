<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole(['super-admin', 'admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('users.create');
    }

    public function update(User $user, User $model): bool
    {
        if (! $user->hasRole(['super-admin', 'admin']) || ! $user->hasPermission('users.edit')) {
            return false;
        }

        // Admin has full access except Super Admin management: an Admin
        // (not Super Admin) may not edit a Super Admin account.
        if ($model->role?->name === 'super-admin' && ! $user->hasRole('super-admin')) {
            return false;
        }

        return true;
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->hasRole(['super-admin', 'admin']) || ! $user->hasPermission('users.delete')) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        if ($model->role?->name === 'super-admin') {
            // Admin has full access except Super Admin management: an Admin
            // (not Super Admin) may not delete a Super Admin account at all.
            if (! $user->hasRole('super-admin')) {
                return false;
            }

            $remainingSuperAdmins = User::whereHas('role', fn ($q) => $q->where('name', 'super-admin'))
                ->where('id', '!=', $model->id)
                ->exists();

            return $remainingSuperAdmins;
        }

        return true;
    }

    public function restore(User $user, User $model): bool
    {
        if (! $user->hasRole(['super-admin', 'admin']) || ! $user->hasPermission('users.edit')) {
            return false;
        }

        return $user->hasRole('super-admin') || $model->role?->name !== 'super-admin';
    }
}
