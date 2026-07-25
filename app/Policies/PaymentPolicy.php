<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Role-based access refactor: Payments stays in Accounts' menu, but
     * Dealer is now excluded (not in Dealer's allowed menu). No permission
     * fallback: Dealer never held payments.view, so this is purely
     * defensive against a future permission grant reopening it silently.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'accounts']);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'accounts']);
    }

    /**
     * Accounts: "Receive Payment". Payments are immutable once recorded —
     * there is no update/delete ability, matching the append-only ledger
     * this module maintains.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'accounts']) && $user->hasPermission('payments.create');
    }
}
