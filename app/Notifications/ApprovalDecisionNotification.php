<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $moduleName,
        private readonly int $recordId,
        private readonly string $recordLabel,
        private readonly string $status,
        private readonly User $actor,
        private readonly ?string $remarks = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'module_name' => $this->moduleName,
            'record_id' => $this->recordId,
            'record_label' => $this->recordLabel,
            'status' => $this->status,
            'actor' => $this->actor->name,
            'remarks' => $this->remarks,
            'message' => "{$this->recordLabel} was marked {$this->status} by {$this->actor->name}".($this->remarks ? " — {$this->remarks}" : ''),
        ];
    }
}
