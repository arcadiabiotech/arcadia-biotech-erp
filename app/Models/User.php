<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'mobile',
        'role_id',
        'dealer_id',
        'password',
        'status',
        'profile_photo',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * User Role
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Dealer Profile
     */
    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function hasRole(string|array $roles): bool
    {
        return $this->role !== null && in_array($this->role->name, (array) $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->role !== null && $this->role->hasPermission($permission);
    }

    /**
     * Dealer assignments this user manages (only meaningful for Marketing-role users).
     */
    public function dealerAssignments()
    {
        return $this->hasMany(DealerAssignment::class, 'marketing_user_id');
    }

    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo ? Storage::disk('public')->url($this->profile_photo) : null;
    }
}
