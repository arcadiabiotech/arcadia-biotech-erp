<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'module',
        'record_id',
        'action',
        'user_id',
        'role_id',
        'old_values',
        'new_values',
        'remarks',
        'ip_address',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Record an audit entry for the given module/record. Captures the
     * acting user, their role, IP and device automatically from the
     * current request context.
     */
    public static function record(string $module, int $recordId, string $action, array $old = [], array $new = [], ?string $remarks = null): self
    {
        $actor = auth()->user();

        return static::create([
            'module' => $module,
            'record_id' => $recordId,
            'action' => $action,
            'user_id' => $actor?->id,
            'role_id' => $actor?->role_id,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'remarks' => $remarks,
            'ip_address' => request()->ip(),
            'device' => request()->userAgent(),
        ]);
    }
}
