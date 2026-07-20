<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Marks a notification read and sends the user to the record it's
     * about. Only bookings exist today, but module_name is stored on every
     * notification so this stays correct as more modules adopt the engine.
     */
    public function read(Request $request, string $notification)
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        $moduleName = $record->data['module_name'] ?? null;
        $recordId = $record->data['record_id'] ?? null;

        if ($moduleName === 'bookings' && $recordId) {
            return redirect()->route('bookings.show', $recordId);
        }

        return back();
    }
}
