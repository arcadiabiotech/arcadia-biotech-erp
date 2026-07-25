<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'roles.manage', 'display_name' => 'Manage roles', 'group' => 'Administration'],
            ['name' => 'permissions.manage', 'display_name' => 'Manage permissions', 'group' => 'Administration'],
            ['name' => 'dealer-assignments.manage', 'display_name' => 'Manage dealer assignments', 'group' => 'Marketing'],

            ['name' => 'dealers.view', 'display_name' => 'View dealers', 'group' => 'Dealers'],
            ['name' => 'dealers.create', 'display_name' => 'Create dealers', 'group' => 'Dealers'],
            ['name' => 'dealers.edit', 'display_name' => 'Edit dealers', 'group' => 'Dealers'],
            ['name' => 'dealers.delete', 'display_name' => 'Delete dealers', 'group' => 'Dealers'],

            ['name' => 'farmers.view', 'display_name' => 'View farmers', 'group' => 'Farmers'],
            ['name' => 'farmers.create', 'display_name' => 'Create farmers', 'group' => 'Farmers'],
            ['name' => 'farmers.edit', 'display_name' => 'Edit farmers', 'group' => 'Farmers'],
            ['name' => 'farmers.delete', 'display_name' => 'Delete farmers', 'group' => 'Farmers'],

            ['name' => 'users.view', 'display_name' => 'View users', 'group' => 'Users'],
            ['name' => 'users.create', 'display_name' => 'Create users', 'group' => 'Users'],
            ['name' => 'users.edit', 'display_name' => 'Edit users', 'group' => 'Users'],
            ['name' => 'users.delete', 'display_name' => 'Delete users', 'group' => 'Users'],

            ['name' => 'bookings.view', 'display_name' => 'View bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.create', 'display_name' => 'Create bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.edit', 'display_name' => 'Edit bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.approve', 'display_name' => 'Approve bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.reject', 'display_name' => 'Reject bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.hold', 'display_name' => 'Hold bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.unlock', 'display_name' => 'Unlock bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.delete', 'display_name' => 'Delete bookings', 'group' => 'Bookings'],

            ['name' => 'payments.view', 'display_name' => 'View payments', 'group' => 'Payments'],
            ['name' => 'payments.create', 'display_name' => 'Receive payments', 'group' => 'Payments'],

            ['name' => 'challans.view', 'display_name' => 'View challans', 'group' => 'Challans'],
            ['name' => 'challans.create', 'display_name' => 'Create challans', 'group' => 'Challans'],

            ['name' => 'invoices.view', 'display_name' => 'View invoices', 'group' => 'Invoices'],
            ['name' => 'invoices.create', 'display_name' => 'Create invoices', 'group' => 'Invoices'],
            ['name' => 'invoices.edit', 'display_name' => 'Edit draft invoices', 'group' => 'Invoices'],
            ['name' => 'invoices.delete', 'display_name' => 'Delete invoices', 'group' => 'Invoices'],
            ['name' => 'invoices.cancel', 'display_name' => 'Cancel invoices', 'group' => 'Invoices'],
            ['name' => 'invoices.unlock', 'display_name' => 'Unlock invoices', 'group' => 'Invoices'],

            ['name' => 'ledger.view', 'display_name' => 'View dealer/farmer ledger', 'group' => 'Ledger'],

            ['name' => 'dispatch.view', 'display_name' => 'View dispatches', 'group' => 'Dispatch'],
            ['name' => 'dispatch.create', 'display_name' => 'Create dispatches', 'group' => 'Dispatch'],
            ['name' => 'dispatch.partial-payment', 'display_name' => 'Dispatch bookings with only partial payment', 'group' => 'Dispatch'],
            ['name' => 'dispatch.no-payment', 'display_name' => 'Dispatch bookings with no payment received yet', 'group' => 'Dispatch'],
            ['name' => 'dispatch.edit', 'display_name' => 'Edit dispatches', 'group' => 'Dispatch'],
            ['name' => 'dispatch.loading', 'display_name' => 'Update dispatch loading status', 'group' => 'Dispatch'],
            ['name' => 'dispatch.deliver', 'display_name' => 'Mark dispatches delivered', 'group' => 'Dispatch'],
            ['name' => 'dispatch.complete', 'display_name' => 'Complete dispatches', 'group' => 'Dispatch'],
            ['name' => 'dispatch.cancel', 'display_name' => 'Cancel dispatches', 'group' => 'Dispatch'],
            ['name' => 'dispatch.unlock', 'display_name' => 'Unlock dispatches', 'group' => 'Dispatch'],
            ['name' => 'dispatch.delete', 'display_name' => 'Delete dispatches', 'group' => 'Dispatch'],

            ['name' => 'dispatch-planning.view', 'display_name' => 'View dispatch plans', 'group' => 'Dispatch Planning'],
            ['name' => 'dispatch-planning.create', 'display_name' => 'Create dispatch plans', 'group' => 'Dispatch Planning'],
            ['name' => 'dispatch-planning.edit', 'display_name' => 'Edit dispatch plans', 'group' => 'Dispatch Planning'],
            ['name' => 'dispatch-planning.approve', 'display_name' => 'Approve or reject dispatch plans', 'group' => 'Dispatch Planning'],
            ['name' => 'dispatch-planning.delete', 'display_name' => 'Delete dispatch plans', 'group' => 'Dispatch Planning'],
            ['name' => 'dispatch-planning.load', 'display_name' => 'Mark plan items loaded', 'group' => 'Dispatch Planning'],
            ['name' => 'dispatch-planning.approve-loading', 'display_name' => 'Approve vehicle loading', 'group' => 'Dispatch Planning'],

            ['name' => 'vehicles.view', 'display_name' => 'View vehicles', 'group' => 'Vehicles'],
            ['name' => 'vehicles.create', 'display_name' => 'Create vehicles', 'group' => 'Vehicles'],
            ['name' => 'vehicles.edit', 'display_name' => 'Edit vehicles', 'group' => 'Vehicles'],
            ['name' => 'vehicles.delete', 'display_name' => 'Delete vehicles', 'group' => 'Vehicles'],

            ['name' => 'reports.view', 'display_name' => 'View reports', 'group' => 'Reports'],

            ['name' => 'settings.manage', 'display_name' => 'Manage settings', 'group' => 'Settings'],

            // Laboratory Daily Operations & Maintenance module. '.approve'
            // gates both the approve and reject actions on a record (same
            // precedent as dispatch-planning.approve covering
            // DispatchPlanController::approve()/::reject()); '.unlock'
            // gates reopening an approved/rejected record back to draft.
            ['name' => 'lab-equipment.view', 'display_name' => 'View lab equipment', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-equipment.create', 'display_name' => 'Add lab equipment', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-equipment.edit', 'display_name' => 'Edit lab equipment', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-equipment.delete', 'display_name' => 'Delete lab equipment', 'group' => 'Laboratory Operations'],

            ['name' => 'lab-checklists.view', 'display_name' => 'View daily checklists', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-checklists.create', 'display_name' => 'Submit daily checklists', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-checklists.edit', 'display_name' => 'Edit daily checklists', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-checklists.delete', 'display_name' => 'Delete daily checklists', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-checklists.approve', 'display_name' => 'Approve/reject daily checklists', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-checklists.unlock', 'display_name' => 'Unlock daily checklists', 'group' => 'Laboratory Operations'],

            ['name' => 'lab-maintenance.view', 'display_name' => 'View equipment maintenance logs', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-maintenance.create', 'display_name' => 'Log equipment maintenance', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-maintenance.edit', 'display_name' => 'Edit equipment maintenance logs', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-maintenance.delete', 'display_name' => 'Delete equipment maintenance logs', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-maintenance.approve', 'display_name' => 'Approve/reject equipment maintenance logs', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-maintenance.unlock', 'display_name' => 'Unlock equipment maintenance logs', 'group' => 'Laboratory Operations'],

            ['name' => 'lab-media.view', 'display_name' => 'View media stock verifications', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-media.create', 'display_name' => 'Log media stock verifications', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-media.edit', 'display_name' => 'Edit media stock verifications', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-media.delete', 'display_name' => 'Delete media stock verifications', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-media.approve', 'display_name' => 'Approve/reject media stock verifications', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-media.unlock', 'display_name' => 'Unlock media stock verifications', 'group' => 'Laboratory Operations'],

            ['name' => 'lab-chemicals.view', 'display_name' => 'View chemical usage logs', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-chemicals.create', 'display_name' => 'Log chemical usage', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-chemicals.edit', 'display_name' => 'Edit chemical usage logs', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-chemicals.delete', 'display_name' => 'Delete chemical usage logs', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-chemicals.approve', 'display_name' => 'Approve/reject chemical usage logs', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-chemicals.unlock', 'display_name' => 'Unlock chemical usage logs', 'group' => 'Laboratory Operations'],

            ['name' => 'lab-contamination.view', 'display_name' => 'View contamination records', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-contamination.create', 'display_name' => 'Log contamination records', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-contamination.edit', 'display_name' => 'Edit contamination records', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-contamination.delete', 'display_name' => 'Delete contamination records', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-contamination.approve', 'display_name' => 'Approve/reject contamination records', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-contamination.unlock', 'display_name' => 'Unlock contamination records', 'group' => 'Laboratory Operations'],

            ['name' => 'lab-reports.view', 'display_name' => 'View laboratory reports', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-reports.create', 'display_name' => 'Create custom report templates', 'group' => 'Laboratory Operations'],
            ['name' => 'lab-reports.edit', 'display_name' => 'Edit custom report templates', 'group' => 'Laboratory Operations'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission + ['status' => true]);
        }

        $allPermissionNames = array_column($permissions, 'name');

        /**
         * Role-based access refactor: this matrix is now the authoritative
         * source of truth for each role's permissions — sync() below fully
         * replaces a role's permission set to match it exactly (previously
         * syncWithoutDetaching() only ever added permissions, so a role
         * could accumulate stale grants that no longer matched its intended
         * menu and no re-seed could ever revoke them).
         *
         * Accounts lost dealers/farmers/invoices/ledger — none of those are
         * in Accounts' current menu (Dashboard, Bookings, Payments,
         * Challans, Dispatch View, Own Profile).
         * Dealer lost invoices/ledger — not in Dealer's current menu
         * (Dashboard, My Profile, My Farmers, My Bookings, Dispatch
         * Status, Documents).
         * Dispatch lost vehicles — not in Dispatch's current menu
         * (Dashboard, Approved Bookings, Dispatch, Delivery, Own Profile).
         *
         * User hierarchy refactor: Marketing gained dealers.create (creates
         * Dealers directly, auto-assigned to themselves) and farmers.create
         * (creates Farmers directly, under one of their own assigned
         * dealers) and Dealer gained farmers.create (creates Farmers
         * directly, auto-scoped to their own dealer_id) — see
         * DealerPolicy::create / FarmerPolicy::create.
         */
        $roleMatrix = [
            'super-admin' => $allPermissionNames,
            'admin' => $allPermissionNames,
            // marketing/dealer gained dispatch.deliver: Dispatch Lifecycle
            // spec has any of Marketing/Dealer/Company Employee/Admin/
            // Accounts/Handling Supervisor able to mark a delivery complete
            // — DispatchPolicy::canDeliver() scopes marketing to their own
            // assigned dealers and dealer to their own dealer_id record.
            'marketing' => ['dealers.view', 'dealers.create', 'farmers.view', 'farmers.create', 'bookings.view', 'bookings.create', 'bookings.edit', 'dispatch.view', 'dispatch.deliver', 'dispatch-planning.view'],
            'dealer' => ['dealers.view', 'farmers.view', 'farmers.create', 'farmers.edit', 'bookings.view', 'dispatch.view', 'dispatch.deliver'],
            // Accounts gained dealers.create/farmers.create: the booking
            // form's inline "register new dealer/farmer" modals post to
            // dealers.store/farmers.store, and Accounts already creates
            // bookings, so it needs the same fallback the other
            // booking-creating role (Marketing) already has.
            // dispatch-planning.view/.create: Dispatch Lifecycle spec has
            // Accounts create the Dispatch Plan itself (Dealer/Farmers/Qty/
            // Date/Route) — added alongside dispatch-planner, not replacing
            // it. dispatch.deliver: Accounts is also one of the roles that
            // may mark a delivery complete per that same spec.
            'accounts' => [
                'bookings.view', 'bookings.create', 'bookings.edit',
                'payments.view', 'payments.create',
                'challans.view', 'challans.create',
                'dispatch.view', 'dispatch.deliver', 'dealers.create', 'farmers.create',
                'dispatch-planning.view', 'dispatch-planning.create',
            ],
            'dispatch' => [
                'dispatch.view', 'dispatch.create', 'dispatch.edit',
                'dispatch.loading', 'dispatch.deliver', 'dispatch.complete',
                'bookings.view',
            ],
            // New Dispatch Planning module roles: Dispatch Planner builds the
            // day's plan and groups bookings onto vehicles; Supervisor only
            // handles the physical loading + approval step, nothing else.
            // vehicles.create lets them register a not-yet-known vehicle
            // inline from the "Add vehicle" flow instead of blocking on an
            // admin (VehicleController::store()'s return_dispatch_plan_id
            // path auto-assigns it back to the plan). farmers.create and
            // dealers.create are the same idea for farmers/dealers: the
            // dispatch plan form's "register new farmer/dealer" modals post
            // to farmers.store/dealers.store inline (see
            // FarmerPolicy::create / DealerPolicy::create). bookings.create
            // is the same idea again: the plan form is Dealer -> Booking ->
            // Dispatch Quantity now, so its "Create Booking" modal posts to
            // bookings.store inline (see BookingPolicy::create()).
            'dispatch-planner' => [
                'dispatch-planning.view', 'dispatch-planning.create', 'dispatch-planning.edit', 'dispatch-planning.approve',
                'bookings.view', 'bookings.create', 'dispatch.view', 'vehicles.view', 'vehicles.create', 'farmers.create', 'dealers.create',
            ],
            // Supervisor gained the Lab Ops approval permissions — this is
            // the same role that already handles the Dispatch Planning
            // loading-approval step, reused here rather than adding a new
            // approver role. dispatch.create/.edit/.loading let them carry
            // a vehicle straight from Approve Loading through to challan +
            // dispatched (DispatchPolicy::create()/update()/canOperate()).
            // Dispatch Lifecycle spec: Handling Supervisor now owns the
            // complete dispatch operation end to end — dispatch-planning
            // .edit/.approve so they can review/edit/approve a plan Accounts
            // created; dispatch-planning.create doubles as what
            // VehicleAssignmentPolicy::create() checks, so it's also what
            // unlocks "Add Vehicle" for them; dispatch.deliver opens
            // markDelivered()/complete()/recordReturn() via the existing
            // canOperate() role whitelist (already includes supervisor) —
            // and the new markVehicleReturned() ability, gated the same way.
            'supervisor' => [
                'dispatch-planning.view', 'dispatch-planning.create', 'dispatch-planning.edit', 'dispatch-planning.approve',
                'dispatch-planning.load', 'dispatch-planning.approve-loading',
                'dispatch.view', 'dispatch.create', 'dispatch.edit', 'dispatch.loading', 'dispatch.deliver',
                'lab-equipment.view', 'lab-reports.view',
                'lab-checklists.view', 'lab-checklists.approve',
                'lab-maintenance.view', 'lab-maintenance.approve',
                'lab-media.view', 'lab-media.approve',
                'lab-chemicals.view', 'lab-chemicals.approve',
                'lab-contamination.view', 'lab-contamination.approve',
            ],
            // Lab Technician: the employee-login role that fills out the
            // daily checklist and the 4 operational logs. Create/edit only
            // — approve/delete/unlock stay with Supervisor/Admin.
            'lab-technician' => [
                'lab-equipment.view',
                'lab-checklists.view', 'lab-checklists.create', 'lab-checklists.edit',
                'lab-maintenance.view', 'lab-maintenance.create', 'lab-maintenance.edit',
                'lab-media.view', 'lab-media.create', 'lab-media.edit',
                'lab-chemicals.view', 'lab-chemicals.create', 'lab-chemicals.edit',
                'lab-contamination.view', 'lab-contamination.create', 'lab-contamination.edit',
            ],
            // Staff = the Dispatch Lifecycle spec's "Company Employee" —
            // maps onto this existing (previously empty) role rather than
            // adding a new one. Only granted enough to view a dispatch and
            // mark it delivered, per that spec's multi-role delivery step.
            'staff' => ['dispatch.view', 'dispatch.deliver'],
        ];

        foreach ($roleMatrix as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');
            $role?->permissions()->sync($permissionIds);
        }
    }
}
