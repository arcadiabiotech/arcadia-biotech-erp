<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Role-based access refactor: Invoices are now Admin/Super Admin only.
     * Accounts and Dealer are deliberately excluded — neither role's
     * current menu includes Invoices — even though Accounts previously
     * generated invoices and Dealer previously viewed their own. No
     * permission fallback here on purpose: Accounts/Dealer still holding
     * an `invoices.*` permission row in the DB must NOT reopen this.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasRole(['super-admin', 'admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']);
    }

    /**
     * Editing is only ever possible while an invoice is still Draft (before
     * the ledger has been debited), to keep the ledger trail trustworthy.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $invoice->status === 'draft';
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasRole(['super-admin', 'admin']);
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('invoices.delete');
    }

    public function generate(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice) && $invoice->status === 'draft';
    }

    /**
     * Admin: "Cancel Invoice".
     */
    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            && $user->hasPermission('invoices.cancel')
            && ! in_array($invoice->status, ['paid', 'cancelled'], true);
    }

    /**
     * Admin: "Unlock Invoice".
     */
    public function unlock(User $user, Invoice $invoice): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            && $user->hasPermission('invoices.unlock')
            && $invoice->status === 'cancelled';
    }
}
