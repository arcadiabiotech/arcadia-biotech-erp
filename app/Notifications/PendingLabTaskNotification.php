<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PendingLabTaskNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $reason,
        private readonly string $message,
        private readonly ?string $moduleName = null,
        private readonly ?int $recordId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reason' => $this->reason,
            'module_name' => $this->moduleName,
            'record_id' => $this->recordId,
            'message' => $this->message,
        ];
    }
}
