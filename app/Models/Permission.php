<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'display_name', 'group', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
