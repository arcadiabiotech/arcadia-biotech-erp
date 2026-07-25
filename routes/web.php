<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\TalukaController;
use App\Http\Controllers\VillageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\DealerAssignmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\VarietyStockController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\ChallanController;
use App\Http\Controllers\ChallanDocumentController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\CrateReturnReportController;
use App\Http\Controllers\TransportReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PublicChallanController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\DispatchPlanController;
use App\Http\Controllers\VehicleAssignmentController;
use App\Http\Controllers\DispatchPlanItemController;
use App\Http\Controllers\LabEquipmentController;
use App\Http\Controllers\LabDailyChecklistController;
use App\Http\Controllers\LabEquipmentMaintenanceLogController;
use App\Http\Controllers\LabMediaStockVerificationController;
use App\Http\Controllers\LabChemicalUsageController;
use App\Http\Controllers\LabContaminationRecordController;
use App\Http\Controllers\LabDashboardController;
use App\Http\Controllers\LabReportsController;
use App\Http\Controllers\ReportTemplateController;

Route::get('/', function () {
    return redirect()->route('login');
});

// The delivery challan's QR code encodes a signed link to this route so a
// farmer/dealer/transporter with no ERP login can scan it and open the
// challan directly — deliberately outside the 'auth' group below, but
// protected by the 'signed' middleware (see PublicChallanController).
Route::get('challan/{dispatch}/view', [PublicChallanController::class, 'show'])
    ->name('challans.public')
    ->middleware('signed');

// Same no-auth-guard/signed-only pattern, for the Dealer/Farmer challan
// documents (see ChallanDocumentController::publicShow()).
Route::get('challan-doc/{challan}/view', [ChallanDocumentController::class, 'publicShow'])
    ->name('challan-docs.public')
    ->middleware('signed');

Route::middleware(['auth'])->group(function () {

    // Dashboard (role-based — DashboardController picks the view and the
    // scoped data per the logged-in user's role).
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Mobile OTP — registration context only (verifying a new Marketing
    // User/Dealer/Farmer's mobile before UserController/DealerController/
    // FarmerController::store() is allowed to save it). Login OTP is a
    // separate unauthenticated flow, see routes/auth.php.
    Route::post('otp/send', [OtpController::class, 'send'])->name('otp.send')->middleware('throttle:10,1');
    Route::post('otp/verify', [OtpController::class, 'verify'])->name('otp.verify')->middleware('throttle:20,1');

    // Dealers (DealerPolicy enforces per-record rules — who can view/edit
    // depends on role + assignment + permission; deletion stays Admin/Super
    // Admin only, gated coarsely below).
    // User hierarchy refactor: Marketing may now create a Dealer too
    // (DealerController::store() auto-assigns it to themselves).
    Route::post('dealers/{dealer}/restore', [DealerController::class, 'restore'])
        ->name('dealers.restore')
        ->withTrashed()
        ->middleware('role:super-admin,admin');
    // Static paths (create) must be registered before the {dealer} wildcard
    // routes (show/edit/update) below, or "/dealers/create" gets swallowed
    // by "/dealers/{dealer}" and 404s instead of resolving to the create form.
    Route::resource('dealers', DealerController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,marketing,dispatch-planner,accounts');
    Route::resource('dealers', DealerController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('dealers', DealerController::class)->except(['create', 'store', 'destroy']);

    // Farmers (FarmerPolicy enforces per-record rules — Marketing is scoped
    // through their assigned dealers, Dealer-role users to their own
    // dealer_id, Accounts can view/edit but never delete; deletion stays
    // Admin/Super Admin only, gated coarsely below).
    // User hierarchy refactor: Dealer may create a Farmer too
    // (FarmerStoreRequest::allowedDealerIds() already forces it to their
    // own dealer_id), and Marketing may create a Farmer under one of their
    // own assigned dealers (allowedDealerIds() scopes that too).
    Route::post('farmers/{farmer}/restore', [FarmerController::class, 'restore'])
        ->name('farmers.restore')
        ->withTrashed()
        ->middleware('role:super-admin,admin');
    // Static paths (create) must precede the {farmer} wildcard routes below,
    // or "/farmers/create" gets swallowed by "/farmers/{farmer}".
    Route::resource('farmers', FarmerController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,marketing,dealer,dispatch-planner,accounts');
    Route::resource('farmers', FarmerController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('farmers', FarmerController::class)->except(['create', 'store', 'destroy']);

    // Bookings (BookingPolicy enforces per-record rules — Marketing/Accounts
    // can create and edit Draft bookings; only Admin/Super Admin can
    // approve, reject, hold, unlock or delete, gated coarsely below).
    // Static paths and the {booking}/action sub-routes must precede the
    // bare {booking} wildcard routes (show/edit/update) below.
    Route::resource('bookings', BookingController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,marketing,accounts,dispatch-planner');
    Route::get('bookings/export', [BookingController::class, 'export'])->name('bookings.export');
    Route::post('bookings/{booking}/restore', [BookingController::class, 'restore'])->name('bookings.restore')->withTrashed()->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/submit', [BookingController::class, 'submit'])->name('bookings.submit');
    Route::post('bookings/{booking}/verify', [BookingController::class, 'verify'])->name('bookings.verify')->middleware('role:super-admin,admin,accounts');
    Route::post('bookings/{booking}/approve', [BookingController::class, 'approve'])->name('bookings.approve')->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/reject', [BookingController::class, 'reject'])->name('bookings.reject')->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/hold', [BookingController::class, 'hold'])->name('bookings.hold')->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/unlock', [BookingController::class, 'unlock'])->name('bookings.unlock')->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/complete-dispatch', [BookingController::class, 'completeDispatch'])->name('bookings.complete-dispatch')->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/receive-payment', [BookingController::class, 'receivePayment'])->name('bookings.receive-payment')->middleware('role:super-admin,admin,accounts');
    Route::get('bookings/{booking}/print', [BookingController::class, 'print'])->name('bookings.print');
    Route::get('bookings/{booking}/pdf', [BookingController::class, 'pdf'])->name('bookings.pdf');
    Route::resource('bookings', BookingController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('bookings', BookingController::class)->except(['create', 'store', 'destroy']);

    // Vehicles (master data for the Dispatch module's transport details).
    Route::post('vehicles/{vehicle}/restore', [VehicleController::class, 'restore'])->name('vehicles.restore')->withTrashed()->middleware('role:super-admin,admin');
    Route::resource('vehicles', VehicleController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,dispatch-planner');
    Route::resource('vehicles', VehicleController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('vehicles', VehicleController::class)->except(['create', 'store', 'destroy', 'show']);

    // Laboratory Daily Operations & Maintenance module. Static/custom-action
    // routes are registered before each resource's wildcard except() block
    // (the same ordering rule as every other module here — /create would
    // otherwise be swallowed by /{model}).

    // Lab Equipment — small master registry, mirrors Vehicles above.
    Route::post('lab-equipment/{lab_equipment}/restore', [LabEquipmentController::class, 'restore'])->name('lab-equipment.restore')->withTrashed()->middleware('role:super-admin,admin');
    Route::resource('lab-equipment', LabEquipmentController::class)->only(['create', 'store', 'destroy'])->middleware('role:super-admin,admin');
    Route::resource('lab-equipment', LabEquipmentController::class)->except(['create', 'store', 'destroy', 'show'])->middleware('role:super-admin,admin,supervisor,lab-technician');

    // Lab Ops Dashboard + Reports — standalone, not nested under any one
    // sub-resource.
    Route::get('lab/dashboard', [LabDashboardController::class, 'index'])->name('lab.dashboard')->middleware('role:super-admin,admin,supervisor,lab-technician');
    Route::get('lab-reports', [LabReportsController::class, 'index'])->name('lab-reports.index')->middleware('role:super-admin,admin,supervisor');
    Route::get('lab-reports/{period}', [LabReportsController::class, 'show'])->name('lab-reports.show')->whereIn('period', ['daily', 'weekly', 'monthly'])->middleware('role:super-admin,admin,supervisor');
    Route::get('lab-reports/{period}/csv', [LabReportsController::class, 'csv'])->name('lab-reports.csv')->whereIn('period', ['daily', 'weekly', 'monthly'])->middleware('role:super-admin,admin,supervisor');
    Route::get('lab-reports/{period}/pdf', [LabReportsController::class, 'pdf'])->name('lab-reports.pdf')->whereIn('period', ['daily', 'weekly', 'monthly'])->middleware('role:super-admin,admin,supervisor');

    // Custom Report Templates — saved module + date-range selections that
    // re-run the same on-the-fly aggregation as Lab Reports above, scoped to
    // just the modules the template was created with. Only Admin/Super Admin
    // can create/edit/delete templates (lab-reports.create/.edit permissions);
    // Supervisor can only view and run existing ones (lab-reports.view).
    Route::resource('report-templates', ReportTemplateController::class)->only(['index'])->middleware('role:super-admin,admin,supervisor');
    Route::resource('report-templates', ReportTemplateController::class)->only(['create', 'store', 'edit', 'update', 'destroy'])->middleware('role:super-admin,admin');
    Route::get('report-templates/{report_template}/run', [ReportTemplateController::class, 'run'])->name('report-templates.run')->middleware('role:super-admin,admin,supervisor');

    // Daily Checklists (Employee Login based + Morning Checklist: UV,
    // Sterilizer, Mopping, Cleaning).
    Route::resource('lab-checklists', LabDailyChecklistController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,lab-technician');
    Route::get('lab-checklists/export', [LabDailyChecklistController::class, 'export'])->name('lab-checklists.export')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-checklists/{lab_checklist}/restore', [LabDailyChecklistController::class, 'restore'])->name('lab-checklists.restore')->withTrashed()->middleware('role:super-admin,admin');
    Route::post('lab-checklists/{lab_checklist}/submit', [LabDailyChecklistController::class, 'submit'])->name('lab-checklists.submit')->middleware('role:super-admin,admin,lab-technician');
    Route::post('lab-checklists/{lab_checklist}/approve', [LabDailyChecklistController::class, 'approve'])->name('lab-checklists.approve')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-checklists/{lab_checklist}/reject', [LabDailyChecklistController::class, 'reject'])->name('lab-checklists.reject')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-checklists/{lab_checklist}/unlock', [LabDailyChecklistController::class, 'unlock'])->name('lab-checklists.unlock')->middleware('role:super-admin,admin');
    Route::resource('lab-checklists', LabDailyChecklistController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('lab-checklists', LabDailyChecklistController::class)->except(['create', 'store', 'destroy']);

    // Equipment Maintenance Logs.
    Route::resource('lab-maintenance', LabEquipmentMaintenanceLogController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,lab-technician');
    Route::get('lab-maintenance/export', [LabEquipmentMaintenanceLogController::class, 'export'])->name('lab-maintenance.export')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-maintenance/{lab_maintenance}/restore', [LabEquipmentMaintenanceLogController::class, 'restore'])->name('lab-maintenance.restore')->withTrashed()->middleware('role:super-admin,admin');
    Route::post('lab-maintenance/{lab_maintenance}/submit', [LabEquipmentMaintenanceLogController::class, 'submit'])->name('lab-maintenance.submit')->middleware('role:super-admin,admin,lab-technician');
    Route::post('lab-maintenance/{lab_maintenance}/approve', [LabEquipmentMaintenanceLogController::class, 'approve'])->name('lab-maintenance.approve')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-maintenance/{lab_maintenance}/reject', [LabEquipmentMaintenanceLogController::class, 'reject'])->name('lab-maintenance.reject')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-maintenance/{lab_maintenance}/unlock', [LabEquipmentMaintenanceLogController::class, 'unlock'])->name('lab-maintenance.unlock')->middleware('role:super-admin,admin');
    Route::resource('lab-maintenance', LabEquipmentMaintenanceLogController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('lab-maintenance', LabEquipmentMaintenanceLogController::class)->except(['create', 'store', 'destroy']);

    // Media Stock Verification.
    Route::resource('lab-media', LabMediaStockVerificationController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,lab-technician');
    Route::get('lab-media/export', [LabMediaStockVerificationController::class, 'export'])->name('lab-media.export')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-media/{lab_media}/restore', [LabMediaStockVerificationController::class, 'restore'])->name('lab-media.restore')->withTrashed()->middleware('role:super-admin,admin');
    Route::post('lab-media/{lab_media}/submit', [LabMediaStockVerificationController::class, 'submit'])->name('lab-media.submit')->middleware('role:super-admin,admin,lab-technician');
    Route::post('lab-media/{lab_media}/approve', [LabMediaStockVerificationController::class, 'approve'])->name('lab-media.approve')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-media/{lab_media}/reject', [LabMediaStockVerificationController::class, 'reject'])->name('lab-media.reject')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-media/{lab_media}/unlock', [LabMediaStockVerificationController::class, 'unlock'])->name('lab-media.unlock')->middleware('role:super-admin,admin');
    // 'media' is the irregular plural of 'medium', so Laravel's auto
    // singularizer would otherwise bind {lab_medium} here instead of
    // {lab_media} — forced explicitly to match the controller/policy/views.
    Route::resource('lab-media', LabMediaStockVerificationController::class)->only(['destroy'])->parameters(['lab-media' => 'lab_media'])->middleware('role:super-admin,admin');
    Route::resource('lab-media', LabMediaStockVerificationController::class)->except(['create', 'store', 'destroy'])->parameters(['lab-media' => 'lab_media']);

    // Chemical Usage.
    Route::resource('lab-chemicals', LabChemicalUsageController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,lab-technician');
    Route::get('lab-chemicals/export', [LabChemicalUsageController::class, 'export'])->name('lab-chemicals.export')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-chemicals/{lab_chemical}/restore', [LabChemicalUsageController::class, 'restore'])->name('lab-chemicals.restore')->withTrashed()->middleware('role:super-admin,admin');
    Route::post('lab-chemicals/{lab_chemical}/submit', [LabChemicalUsageController::class, 'submit'])->name('lab-chemicals.submit')->middleware('role:super-admin,admin,lab-technician');
    Route::post('lab-chemicals/{lab_chemical}/approve', [LabChemicalUsageController::class, 'approve'])->name('lab-chemicals.approve')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-chemicals/{lab_chemical}/reject', [LabChemicalUsageController::class, 'reject'])->name('lab-chemicals.reject')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-chemicals/{lab_chemical}/unlock', [LabChemicalUsageController::class, 'unlock'])->name('lab-chemicals.unlock')->middleware('role:super-admin,admin');
    Route::resource('lab-chemicals', LabChemicalUsageController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('lab-chemicals', LabChemicalUsageController::class)->except(['create', 'store', 'destroy']);

    // Contamination Records.
    Route::resource('lab-contamination', LabContaminationRecordController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,lab-technician');
    Route::get('lab-contamination/export', [LabContaminationRecordController::class, 'export'])->name('lab-contamination.export')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-contamination/{lab_contamination}/restore', [LabContaminationRecordController::class, 'restore'])->name('lab-contamination.restore')->withTrashed()->middleware('role:super-admin,admin');
    Route::post('lab-contamination/{lab_contamination}/submit', [LabContaminationRecordController::class, 'submit'])->name('lab-contamination.submit')->middleware('role:super-admin,admin,lab-technician');
    Route::post('lab-contamination/{lab_contamination}/approve', [LabContaminationRecordController::class, 'approve'])->name('lab-contamination.approve')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-contamination/{lab_contamination}/reject', [LabContaminationRecordController::class, 'reject'])->name('lab-contamination.reject')->middleware('role:super-admin,admin,supervisor');
    Route::post('lab-contamination/{lab_contamination}/unlock', [LabContaminationRecordController::class, 'unlock'])->name('lab-contamination.unlock')->middleware('role:super-admin,admin');
    Route::resource('lab-contamination', LabContaminationRecordController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('lab-contamination', LabContaminationRecordController::class)->except(['create', 'store', 'destroy']);

    // Dispatch Planning — the pre-dispatch layer (plan a day/route, group
    // bookings onto vehicles, Supervisor loads + approves). Feeds into the
    // existing Dispatch lifecycle below once loading is approved (Phase 4).
    Route::resource('dispatch-plans', DispatchPlanController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,dispatch-planner,accounts');
    Route::resource('dispatch-plans', DispatchPlanController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('dispatch-plans', DispatchPlanController::class)->except(['create', 'store', 'destroy']);
    Route::post('dispatch-plans/{dispatchPlan}/approve', [DispatchPlanController::class, 'approve'])->name('dispatch-plans.approve')->middleware('role:super-admin,admin,dispatch-planner,supervisor');
    Route::post('dispatch-plans/{dispatchPlan}/reject', [DispatchPlanController::class, 'reject'])->name('dispatch-plans.reject')->middleware('role:super-admin,admin,dispatch-planner,supervisor');
    Route::post('dispatch-plans/{dispatchPlan}/vehicle-assignments', [VehicleAssignmentController::class, 'store'])->name('vehicle-assignments.store')->middleware('role:super-admin,admin,dispatch-planner,supervisor');
    Route::put('vehicle-assignments/{vehicleAssignment}', [VehicleAssignmentController::class, 'update'])->name('vehicle-assignments.update')->middleware('role:super-admin,admin,dispatch-planner');
    Route::delete('vehicle-assignments/{vehicleAssignment}', [VehicleAssignmentController::class, 'destroy'])->name('vehicle-assignments.destroy')->middleware('role:super-admin,admin');
    // Phase 4: Supervisor physically loads each booking (toggle per item),
    // then approves the whole vehicle once every item is loaded — approval
    // auto-creates the real Dispatch records (DispatchPlanningService::approveLoading()).
    Route::post('vehicle-assignments/{vehicleAssignment}/approve-loading', [VehicleAssignmentController::class, 'approveLoading'])->name('vehicle-assignments.approve-loading')->middleware('role:super-admin,admin,supervisor');
    // One-click "Vehicle Loaded": marks every item loaded, approves loading,
    // creates the Dispatch and generates its challan — all inline on the
    // Dispatches index page, no redirect into Dispatch Planning.
    Route::post('vehicle-assignments/{vehicleAssignment}/load-vehicle', [DispatchController::class, 'loadVehicle'])->name('vehicle-assignments.load-vehicle')->middleware('role:super-admin,admin,supervisor');
    Route::post('vehicle-assignments/{vehicleAssignment}/items', [DispatchPlanItemController::class, 'store'])->name('dispatch-plan-items.store')->middleware('role:super-admin,admin,dispatch-planner');
    Route::put('dispatch-plan-items/{dispatchPlanItem}/reassign', [DispatchPlanItemController::class, 'reassign'])->name('dispatch-plan-items.reassign')->middleware('role:super-admin,admin,dispatch-planner');
    Route::post('dispatch-plan-items/{dispatchPlanItem}/toggle-loaded', [DispatchPlanItemController::class, 'toggleLoaded'])->name('dispatch-plan-items.toggle-loaded')->middleware('role:super-admin,admin,supervisor');
    Route::delete('dispatch-plan-items/{dispatchPlanItem}', [DispatchPlanItemController::class, 'destroy'])->name('dispatch-plan-items.destroy')->middleware('role:super-admin,admin,dispatch-planner');

    // Dispatch (DispatchPolicy enforces per-record rules — Dispatch-role
    // users can create dispatches for Approved bookings and drive them
    // through Loading/Vehicle Out/Delivered; only Admin/Super Admin can
    // cancel or unlock, gated coarsely below).
    // Static paths and the {dispatch}/action sub-routes must precede the
    // bare {dispatch} wildcard routes (show/edit/update) below.
    Route::resource('dispatches', DispatchController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,dispatch,supervisor');
    Route::get('dispatches/export', [DispatchController::class, 'export'])->name('dispatches.export');
    Route::post('dispatches/{dispatch}/restore', [DispatchController::class, 'restore'])->name('dispatches.restore')->withTrashed()->middleware('role:super-admin,admin');
    Route::post('dispatches/{dispatch}/submit', [DispatchController::class, 'submit'])->name('dispatches.submit')->middleware('role:super-admin,admin,dispatch,supervisor');
    Route::post('dispatches/{dispatch}/start-loading', [DispatchController::class, 'startLoading'])->name('dispatches.start-loading')->middleware('role:super-admin,admin,dispatch,supervisor');
    Route::post('dispatches/{dispatch}/vehicle-out', [DispatchController::class, 'vehicleOut'])->name('dispatches.vehicle-out')->middleware('role:super-admin,admin,dispatch,supervisor');
    // Dispatch Lifecycle spec: Marketing/Dealer/Company Employee (staff)/
    // Accounts/Supervisor can all mark a delivery complete alongside
    // Dispatch/Admin — DispatchPolicy::canDeliver() does the actual (and
    // dealer/marketing-scoped) gating; this middleware just needs to admit
    // the roles at all.
    Route::post('dispatches/{dispatch}/deliver', [DispatchController::class, 'markDelivered'])->name('dispatches.deliver')->middleware('role:super-admin,admin,dispatch,supervisor,marketing,dealer,accounts,staff');
    Route::post('dispatches/{dispatch}/complete', [DispatchController::class, 'complete'])->name('dispatches.complete')->middleware('role:super-admin,admin,dispatch');
    Route::post('dispatches/{dispatch}/vehicle-returned', [DispatchController::class, 'vehicleReturned'])->name('dispatches.vehicle-returned')->middleware('role:super-admin,admin,dispatch,supervisor');
    Route::post('dispatches/{dispatch}/record-return', [DispatchController::class, 'recordReturn'])->name('dispatches.record-return')->middleware('role:super-admin,admin,dispatch,supervisor');
    Route::post('dispatches/{dispatch}/cancel', [DispatchController::class, 'cancel'])->name('dispatches.cancel')->middleware('role:super-admin,admin');
    Route::post('dispatches/{dispatch}/unlock', [DispatchController::class, 'unlock'])->name('dispatches.unlock')->middleware('role:super-admin,admin');
    Route::get('dispatches/{dispatch}/print', [DispatchController::class, 'print'])->name('dispatches.print');
    Route::get('dispatches/{dispatch}/pdf', [DispatchController::class, 'pdf'])->name('dispatches.pdf');
    Route::post('dispatches/{dispatch}/email-challan', [DispatchController::class, 'emailChallan'])->name('dispatches.email-challan')->middleware('throttle:5,1');

    // Dealer (Master) + Farmer challans — see ChallanDocumentController. Not
    // to be confused with challans.index/export above (the unrelated
    // read-only Dispatch.challan_no listing).
    Route::get('dispatches/{dispatch}/challans/farmers/print', [ChallanDocumentController::class, 'farmersPrint'])->name('dispatches.challans.farmers-print');
    Route::get('dispatches/{dispatch}/challans/farmers/pdf', [ChallanDocumentController::class, 'farmersPdf'])->name('dispatches.challans.farmers-pdf');
    Route::get('dispatches/{dispatch}/challans/print-all', [ChallanDocumentController::class, 'printAll'])->name('dispatches.challans.print-all');
    Route::get('dispatches/{dispatch}/challans/zip', [ChallanDocumentController::class, 'zip'])->name('dispatches.challans.zip');
    Route::get('challan-docs/{challan}/print', [ChallanDocumentController::class, 'show'])->name('challan-docs.show');
    Route::get('challan-docs/{challan}/pdf', [ChallanDocumentController::class, 'pdf'])->name('challan-docs.pdf');

    Route::resource('dispatches', DispatchController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('dispatches', DispatchController::class)->except(['create', 'store', 'destroy']);

    // Invoices (InvoicePolicy enforces per-record rules — Accounts generates
    // an invoice from a Completed dispatch, edits it while still Draft, and
    // receives payments; only Admin/Super Admin can cancel or unlock an
    // invoice, gated coarsely below; Accounts may delete unless Paid, which
    // InvoicePolicy::delete enforces per-record).
    // Static paths and the {invoice}/action sub-routes must precede the
    // bare {invoice} wildcard routes (show/edit/update) below.
    // Role-based access refactor: Invoices is now Admin/Super Admin only
    // at every route (previously Accounts could create/generate/delete).
    Route::resource('invoices', InvoiceController::class)->only(['create', 'store'])->middleware('role:super-admin,admin');
    Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export')->middleware('role:super-admin,admin');
    Route::post('invoices/{invoice}/restore', [InvoiceController::class, 'restore'])->name('invoices.restore')->withTrashed()->middleware('role:super-admin,admin');
    Route::post('invoices/{invoice}/generate', [InvoiceController::class, 'generate'])->name('invoices.generate')->middleware('role:super-admin,admin');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel')->middleware('role:super-admin,admin');
    Route::post('invoices/{invoice}/unlock', [InvoiceController::class, 'unlock'])->name('invoices.unlock')->middleware('role:super-admin,admin');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print')->middleware('role:super-admin,admin');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf')->middleware('role:super-admin,admin');
    Route::resource('invoices', InvoiceController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('invoices', InvoiceController::class)->except(['create', 'store', 'destroy'])->middleware('role:super-admin,admin');

    // Payments (PaymentPolicy enforces per-record rules — Accounts receives
    // payments against a Generated/Partially Paid invoice; payments are
    // immutable once recorded, so there is no edit/delete route).
    Route::resource('payments', PaymentController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,accounts');
    Route::get('payments/export', [PaymentController::class, 'export'])->name('payments.export');
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::get('payments/{payment}/receipt-pdf', [PaymentController::class, 'receiptPdf'])->name('payments.receipt-pdf');
    Route::resource('payments', PaymentController::class)->only(['index', 'show']);

    // Ledger (read-only reporting over ledger_entries — Outstanding Report,
    // Dealer Ledger and Farmer Ledger statements). Role-based access
    // refactor: Admin/Super Admin only now (previously also Accounts and,
    // for their own dealer, Dealer) — route middleware here is
    // defense-in-depth alongside LedgerController's own abort_unless checks.
    Route::get('ledger', [LedgerController::class, 'outstanding'])->name('ledger.outstanding')->middleware('role:super-admin,admin');
    Route::get('ledger/export', [LedgerController::class, 'exportOutstanding'])->name('ledger.export')->middleware('role:super-admin,admin');
    Route::get('ledger/dealers/{dealer}', [LedgerController::class, 'dealer'])->name('ledger.dealer')->middleware('role:super-admin,admin');
    Route::get('ledger/dealers/{dealer}/export', [LedgerController::class, 'exportDealer'])->name('ledger.dealer-export')->middleware('role:super-admin,admin');
    Route::get('ledger/farmers/{farmer}', [LedgerController::class, 'farmer'])->name('ledger.farmer')->middleware('role:super-admin,admin');
    Route::get('ledger/farmers/{farmer}/export', [LedgerController::class, 'exportFarmer'])->name('ledger.farmer-export')->middleware('role:super-admin,admin');

    // Challans (read-only view over Dispatch.challan_no — see ChallanController).
    Route::get('challans', [ChallanController::class, 'index'])->name('challans.index')->middleware('role:super-admin,admin,accounts');
    Route::get('challans/export', [ChallanController::class, 'export'])->name('challans.export')->middleware('role:super-admin,admin,accounts');

    // Reports (landing page indexing the exports each module already owns).
    // Role-based access refactor: Admin/Super Admin only (Marketing must
    // NEVER see Reports; Accounts is not listed for it either).
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index')->middleware('role:super-admin,admin');

    // Crate Return Report — also reachable by the Dispatch role directly
    // (they record returns day-to-day), not just from the Admin-only
    // Reports hub above.
    Route::get('reports/crate-returns', [CrateReturnReportController::class, 'index'])->name('reports.crate-returns')->middleware('role:super-admin,admin,dispatch');
    Route::get('reports/crate-returns/csv', [CrateReturnReportController::class, 'csv'])->name('reports.crate-returns.csv')->middleware('role:super-admin,admin,dispatch');
    Route::get('reports/crate-returns/pdf', [CrateReturnReportController::class, 'pdf'])->name('reports.crate-returns.pdf')->middleware('role:super-admin,admin,dispatch');

    // Transport Cost Report (Step 7A) — same access pattern as Crate Return
    // Report above: Dispatch role reaches it directly, Admin via the hub.
    Route::get('reports/transport-cost', [TransportReportController::class, 'index'])->name('reports.transport-cost')->middleware('role:super-admin,admin,dispatch');
    Route::get('reports/transport-cost/csv', [TransportReportController::class, 'csv'])->name('reports.transport-cost.csv')->middleware('role:super-admin,admin,dispatch');

    // Variety Stock (the "actual stock" side of the Stock Reservation engine).
    Route::get('variety-stocks', [VarietyStockController::class, 'index'])->name('variety-stocks.index')->middleware('role:super-admin,admin');
    Route::put('variety-stocks/{varietyStock}', [VarietyStockController::class, 'update'])->name('variety-stocks.update')->middleware('role:super-admin,admin');

    // Notifications (Approval Engine's "Approval Notification" feature).
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // States, Districts, Talukas (geography master data — Admin/Super Admin
    // only. Role-based access refactor: these had ZERO authorization of any
    // kind before — no route middleware, no Policy, no controller check —
    // meaning any authenticated user of any role could create/edit/delete
    // them. Not listed in any non-admin role's menu, so gated here.)
    Route::resource('states', StateController::class)->middleware('role:super-admin,admin');
    Route::resource('districts', DistrictController::class)->middleware('role:super-admin,admin');
    Route::resource('talukas', TalukaController::class)->middleware('role:super-admin,admin');

    // Users (role middleware is a coarse first gate; UserPolicy enforces the
    // fine-grained per-record rules, including that Admins cannot manage
    // Super Admin accounts).
    Route::post('users/{user}/restore', [UserController::class, 'restore'])
        ->name('users.restore')
        ->withTrashed()
        ->middleware('role:super-admin,admin');
    Route::resource('users', UserController::class)->except('show')->middleware('role:super-admin,admin');

    // Manual rating override — Super Admin only, deliberately excluding
    // Admin (unlike every other role:super-admin,admin gate above).
    Route::middleware('role:super-admin')->group(function () {
        Route::put('ratings/{type}/{id}', [RatingController::class, 'update'])->name('ratings.update')->where('type', 'dealer|farmer|user');
        Route::post('ratings/{type}/{id}/reset', [RatingController::class, 'reset'])->name('ratings.reset')->where('type', 'dealer|farmer|user');
    });

    Route::resource('roles', RoleController::class)->except('show')->middleware('role:super-admin,admin');
    Route::resource('permissions', PermissionController::class)->except('show')->middleware('permission:permissions.manage');

    // Dealer Assignments — Admin/Super Admin only. Role-based access
    // refactor: Marketing previously saw the assignment-management index
    // too, but "Dealer Assignments" is not in Marketing's current menu
    // (they only see their own already-assigned Dealers/Farmers via the
    // Dealers/Farmers modules, which remain scoped for them independently).
    Route::resource('dealer-assignments', DealerAssignmentController::class)
        ->except('show')
        ->middleware('role:super-admin,admin');

    // Villages (geography master data — Admin/Super Admin only, same as
    // States/Districts/Talukas above).
    Route::resource('villages', VillageController::class)->middleware('role:super-admin,admin');
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
