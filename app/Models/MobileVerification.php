<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileVerification extends Model
{
    public const PURPOSES = ['registration', 'login'];

    public const STATUSES = ['pending', 'verified', 'expired', 'failed'];

    public const MAX_ATTEMPTS = 5;

    public const MAX_RESENDS = 3;

    public const EXPIRES_IN_MINUTES = 5;

    protected $fillable = [
        'user_id',
        'mobile',
        'otp',
        'purpose',
        'status',
        'verified_at',
        'expires_at',
        'attempts',
        'resend_count',
        'ip_address',
        'device',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'attempts' => 'integer',
        'resend_count' => 'integer',
    ];

    protected $hidden = [
        'otp',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
