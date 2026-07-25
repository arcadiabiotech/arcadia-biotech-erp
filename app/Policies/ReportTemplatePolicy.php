<?php

namespace App\Policies;

use App\Models\ReportTemplate;
use App\Models\User;

class ReportTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'supervisor']) && $user->hasPermission('lab-reports.view');
    }

    public function view(User $user, ReportTemplate $reportTemplate): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-reports.create');
    }

    public function update(User $user, ReportTemplate $reportTemplate): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-reports.edit');
    }

    public function delete(User $user, ReportTemplate $reportTemplate): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-reports.edit');
    }
}
