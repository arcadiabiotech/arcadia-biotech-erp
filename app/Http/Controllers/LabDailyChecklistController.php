<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabDailyChecklistStoreRequest;
use App\Http\Requests\LabDailyChecklistUpdateRequest;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\LabChecklistItem;
use App\Models\LabDailyChecklist;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LabNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LabDailyChecklistController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly LabNumberGenerator $numbers,
    ) {
        $this->authorizeResource(LabDailyChecklist::class, 'lab_checklist');
    }

    public function index(Request $request)
    {
        $checklists = $this->filtered($request)
            ->with(['employee', 'checklistItems'])
            ->latest('checklist_date')
            ->paginate(15)
            ->withQueryString();

        return view('lab-checklists.index', [
            'checklists' => $checklists,
            'trashed' => $request->boolean('trashed'),
        ]);
    }

    public function create(Request $request)
    {
        return view('lab-checklists.form', [
            'labChecklist' => new LabDailyChecklist(['checklist_date' => now()->toDateString()]),
            'employees' => $this->employeeOptions($request->user()),
        ]);
    }

    public function store(LabDailyChecklistStoreRequest $request)
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['employee_id'] = $request->user()->hasRole(['super-admin', 'admin']) && $request->filled('employee_id')
            ? $request->input('employee_id')
            : $request->user()->id;
        $data['checklist_no'] = $this->numbers->next(LabDailyChecklist::class, 'checklist_no', 'LC');
        $data['status'] = 'draft';
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('lab-checklists/photos', 'public');
        }

        $checklist = DB::transaction(function () use ($data, $items) {
            $checklist = LabDailyChecklist::create($data);
            $this->syncChecklistItems($checklist, $items);

            return $checklist;
        });

        ActivityLog::record('lab-checklists', $checklist->id, 'create', [], $checklist->fresh('checklistItems')->toArray());

        return redirect()->route('lab-checklists.show', $checklist)->with('success', 'Daily checklist saved.');
    }

    public function show(LabDailyChecklist $labChecklist)
    {
        $labChecklist->load(['employee', 'createdBy', 'updatedBy', 'checklistItems']);

        $activity = ActivityLog::where('module', 'lab-checklists')->where('record_id', $labChecklist->id)->latest()->limit(30)->get();
        $approvalLevels = $this->approvals->levelsFor('lab-checklists', $labChecklist->id)->load(['approvedBy', 'rejectedBy', 'unlockBy']);

        return view('lab-checklists.show', compact('labChecklist', 'activity', 'approvalLevels'));
    }

    public function edit(Request $request, LabDailyChecklist $labChecklist)
    {
        $labChecklist->load('checklistItems');

        return view('lab-checklists.form', [
            'labChecklist' => $labChecklist,
            'employees' => $this->employeeOptions($request->user()),
        ]);
    }

    public function update(LabDailyChecklistUpdateRequest $request, LabDailyChecklist $labChecklist)
    {
        $original = $labChecklist->toArray();

        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);
        $data['updated_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            if ($labChecklist->photo) {
                Storage::disk('public')->delete($labChecklist->photo);
            }
            $data['photo'] = $request->file('photo')->store('lab-checklists/photos', 'public');
        }

        DB::transaction(function () use ($labChecklist, $data, $items) {
            $labChecklist->update($data);
            $this->syncChecklistItems($labChecklist, $items);
        });

        ActivityLog::record('lab-checklists', $labChecklist->id, 'update', $original, $labChecklist->fresh('checklistItems')->toArray());

        return redirect()->route('lab-checklists.show', $labChecklist)->with('success', 'Daily checklist updated.');
    }

    public function destroy(LabDailyChecklist $labChecklist)
    {
        DB::transaction(function () use ($labChecklist) {
            $labChecklist->update(['deleted_by' => auth()->id()]);
            $labChecklist->delete();
        });

        ActivityLog::record('lab-checklists', $labChecklist->id, 'delete');

        return redirect()->route('lab-checklists.index')->with('success', 'Checklist deleted successfully.');
    }

    public function restore(LabDailyChecklist $labChecklist)
    {
        $this->authorize('restore', $labChecklist);

        DB::transaction(function () use ($labChecklist) {
            $labChecklist->restore();
            $labChecklist->update(['deleted_by' => null]);
        });

        ActivityLog::record('lab-checklists', $labChecklist->id, 'update', [], [], 'Checklist restored');

        return redirect()->route('lab-checklists.index')->with('success', 'Checklist restored successfully.');
    }

    public function submit(LabDailyChecklist $labChecklist)
    {
        $this->authorize('submit', $labChecklist);

        $original = $labChecklist->only('status');
        $labChecklist->update(['status' => 'pending']);

        ActivityLog::record('lab-checklists', $labChecklist->id, 'update', $original, ['status' => 'pending'], 'Submitted for supervisor approval');

        return back()->with('success', 'Checklist submitted for supervisor approval.');
    }

    public function approve(Request $request, LabDailyChecklist $labChecklist)
    {
        $this->authorize('approve', $labChecklist);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'signature_data' => ['required', 'string'],
        ]);

        $signature = $this->storeSignature($validated['signature_data']);
        $original = $labChecklist->only('status');

        DB::transaction(function () use ($labChecklist, $validated, $request, $signature) {
            $labChecklist->update(['status' => 'approved']);

            $this->approvals->record(
                'lab-checklists',
                $labChecklist->id,
                Approval::LEVEL_VERIFICATION,
                'approved',
                $request->user(),
                $validated['remarks'] ?? null,
                $labChecklist->checklist_no,
                $this->notifyRecipients($labChecklist->employee_id),
                $signature,
            );
        });

        ActivityLog::record('lab-checklists', $labChecklist->id, 'approve', $original, ['status' => 'approved'], $validated['remarks'] ?? null);

        return back()->with('success', 'Checklist approved.');
    }

    public function reject(Request $request, LabDailyChecklist $labChecklist)
    {
        $this->authorize('approve', $labChecklist);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $original = $labChecklist->only('status');

        DB::transaction(function () use ($labChecklist, $validated, $request) {
            $labChecklist->update(['status' => 'rejected']);

            $this->approvals->record(
                'lab-checklists',
                $labChecklist->id,
                Approval::LEVEL_VERIFICATION,
                'rejected',
                $request->user(),
                $validated['remarks'],
                $labChecklist->checklist_no,
                $this->notifyRecipients($labChecklist->employee_id),
            );
        });

        ActivityLog::record('lab-checklists', $labChecklist->id, 'reject', $original, ['status' => 'rejected'], $validated['remarks']);

        return back()->with('success', 'Checklist rejected.');
    }

    public function unlock(Request $request, LabDailyChecklist $labChecklist)
    {
        $this->authorize('unlock', $labChecklist);

        $original = $labChecklist->only('status');

        DB::transaction(function () use ($labChecklist, $request) {
            $labChecklist->update(['status' => 'draft']);

            $this->approvals->record(
                'lab-checklists',
                $labChecklist->id,
                Approval::LEVEL_VERIFICATION,
                'unlocked',
                $request->user(),
                null,
                $labChecklist->checklist_no,
                $this->notifyRecipients($labChecklist->employee_id),
            );
        });

        ActivityLog::record('lab-checklists', $labChecklist->id, 'unlock', $original, ['status' => 'draft']);

        return back()->with('success', 'Checklist unlocked for editing.');
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', LabDailyChecklist::class);

        $checklists = $this->filtered($request)->with(['employee', 'checklistItems'])->latest('checklist_date')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="lab-checklists.csv"',
        ];

        return response()->streamDownload(function () use ($checklists) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Checklist No', 'Employee', 'Date', 'Items Done', 'Total Items', 'Compliance %', 'Status']);
            foreach ($checklists as $checklist) {
                fputcsv($out, [
                    $checklist->checklist_no,
                    $checklist->employee?->name,
                    $checklist->checklist_date?->format('Y-m-d'),
                    $checklist->checklistItems->where('is_done', true)->count(),
                    count(LabDailyChecklist::CHECKLIST_ITEMS),
                    $checklist->compliance_score,
                    $checklist->status,
                ]);
            }
            fclose($out);
        }, 'lab-checklists.csv', $headers);
    }

    private function filtered(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $trashed = $request->boolean('trashed');

        return LabDailyChecklist::query()
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->when($user->hasRole('lab-technician'), fn ($q) => $q->where('employee_id', $user->id))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->input('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('checklist_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('checklist_date', '<=', $request->input('to_date')))
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('checklist_no', 'like', "%{$search}%")
                ->orWhereHas('employee', fn ($qqq) => $qqq->where('name', 'like', "%{$search}%"))));
    }

    /**
     * Upserts one lab_checklist_items row per catalogue entry (see
     * LabDailyChecklist::CHECKLIST_ITEMS) from the submitted `items[key]`
     * form data — items the user left untouched are recorded as not done,
     * so the item list always matches the full catalogue exactly.
     */
    private function syncChecklistItems(LabDailyChecklist $checklist, array $itemsInput): void
    {
        foreach (LabDailyChecklist::CHECKLIST_ITEMS as $key => $meta) {
            $entry = $itemsInput[$key] ?? [];

            LabChecklistItem::updateOrCreate(
                ['lab_daily_checklist_id' => $checklist->id, 'item_key' => $key],
                [
                    'is_done' => (bool) ($entry['done'] ?? false),
                    'time_recorded' => $entry['time'] ?? null,
                    'chemical_used' => $meta['chemical'] ? ($entry['chemical'] ?? null) : null,
                ]
            );
        }
    }

    private function employeeOptions(User $user)
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return User::whereHas('role', fn ($q) => $q->where('name', 'lab-technician'))->where('status', true)->orderBy('name')->get();
        }

        return collect([$user]);
    }

    /**
     * Supervisors/admins awaiting decisions, plus the checklist's own
     * employee (so they learn the outcome) — excluding whoever is acting
     * right now, same shape as BookingController::notifyRecipients().
     */
    private function notifyRecipients(int $employeeId)
    {
        $approvers = User::whereHas('role', fn ($q) => $q->whereIn('name', ['supervisor', 'admin', 'super-admin']))
            ->where('status', true)
            ->get();

        return $approvers->push(User::find($employeeId))
            ->filter()
            ->unique('id')
            ->reject(fn ($user) => $user->id === auth()->id());
    }

    /**
     * Decodes the signature pad's base64 PNG payload and stores it under
     * the same disk/convention as Dispatch's photo/signature uploads.
     */
    private function storeSignature(string $dataUrl): string
    {
        $encoded = preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl);
        $path = 'approvals/signatures/'.uniqid('sig_', true).'.png';

        Storage::disk('public')->put($path, base64_decode($encoded));

        return $path;
    }
}
