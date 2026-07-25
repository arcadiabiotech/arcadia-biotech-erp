<?php

namespace App\Models\Concerns;

use App\Models\Approval;

trait HasSupervisorApproval
{
    public function latestApproval(): ?Approval
    {
        return Approval::forRecord(static::APPROVAL_MODULE, $this->id)
            ->where('approval_level', Approval::LEVEL_VERIFICATION)
            ->first();
    }

    public function approvalStatus(): ?string
    {
        return $this->latestApproval()?->status;
    }
}
