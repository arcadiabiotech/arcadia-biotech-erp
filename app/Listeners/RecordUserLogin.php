<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Login;

class RecordUserLogin
{
    public function handle(Login $event): void
    {
        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();

        ActivityLog::record('users', $event->user->id, 'login');
    }
}
