<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Logout;

class RecordUserLogout
{
    public function handle(Logout $event): void
    {
        if ($event->user) {
            ActivityLog::record('users', $event->user->id, 'logout');
        }
    }
}
