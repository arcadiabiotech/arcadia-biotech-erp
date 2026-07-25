<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Marks a notification read and sends the user to the record it's
     * about. module_name is stored on every notification, so growing this
     * map is all that's needed as more modules adopt the engine.
     */
    private const ROUTE_BY_MODULE = [
        'bookings' => 'bookings.show',
        'lab-checklists' => 'lab-checklists.show',
        'lab-maintenance' => 'lab-maintenance.show',
        'lab-media' => 'lab-media.show',
        'lab-chemicals' => 'lab-chemicals.show',
        'lab-contamination' => 'lab-contamination.show',
    ];

    public function read(Request $request, string $notification)
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        $moduleName = $record->data['module_name'] ?? null;
        $recordId = $record->data['record_id'] ?? null;

        if ($recordId && isset(self::ROUTE_BY_MODULE[$moduleName])) {
            return redirect()->route(self::ROUTE_BY_MODULE[$moduleName], $recordId);
        }

        return back();
    }
}
