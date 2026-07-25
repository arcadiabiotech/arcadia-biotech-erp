<?php

namespace App\Http\Controllers;

use App\Models\LabChecklistItem;
use App\Models\LabChemicalUsage;
use App\Models\LabContaminationRecord;
use App\Models\LabDailyChecklist;
use App\Models\LabEquipment;
use App\Models\LabEquipmentMaintenanceLog;
use App\Models\LabMediaStockVerification;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LabReportsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin', 'supervisor']), 403);

        return view('lab-reports.index');
    }

    public function show(Request $request, string $period)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin', 'supervisor']), 403);

        $report = $this->build($period, $request->date('date'));

        return view('lab-reports.show', compact('report', 'period'));
    }

    public function csv(Request $request, string $period)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin', 'supervisor']), 403);

        $report = $this->build($period, $request->date('date'));

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"lab-report-{$period}.csv\"",
        ];

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Metric', 'Value']);
            fputcsv($out, ['Period', $report['label']]);
            fputcsv($out, ['Checklist Compliance %', $report['compliance']]);
            foreach (LabDailyChecklist::CHECKLIST_ITEMS as $key => $meta) {
                fputcsv($out, [$meta['label'].' %', $report['itemCompliance'][$key]]);
            }
            fputcsv($out, ['Checklists Approved', $report['checklistsApproved']]);
            fputcsv($out, ['Checklists Submitted', $report['checklistsSubmitted']]);
            fputcsv($out, ['Equipment Maintenance Logs', $report['maintenanceCount']]);
            fputcsv($out, ['Overdue Maintenance', $report['overdueMaintenance']]);
            fputcsv($out, ['Media Verifications', $report['mediaCount']]);
            fputcsv($out, ['Low Stock Media Items', $report['lowStockMedia']]);
            fputcsv($out, ['Chemical Usage Entries', $report['chemicalCount']]);
            fputcsv($out, ['Contamination Incidents', $report['contaminationCount']]);
            fputcsv($out, ['Critical Contamination Incidents', $report['criticalContamination']]);
            fclose($out);
        }, "lab-report-{$period}.csv", $headers);
    }

    public function pdf(Request $request, string $period)
    {
        abort_unless($request->user()->hasRole(['super-admin', 'admin', 'supervisor']), 403);

        $report = $this->build($period, $request->date('date'));

        return Pdf::loadView('lab-reports.pdf', compact('report', 'period'))
            ->download("lab-report-{$period}-".now()->format('Ymd').'.pdf');
    }

    /**
     * Compliance % is the Daily Checklist metric specifically: checklists
     * approved for the period's employee-days, divided by the expected
     * employee-days (active lab-technician headcount x days in period).
     * The other 4 modules are event logs, not daily quotas, so they surface
     * as their own operational tiles rather than folding into one blended
     * percentage — see the module's implementation plan for the rationale.
     */
    private function build(string $period, ?Carbon $anchor): array
    {
        $anchor = $anchor ? Carbon::parse($anchor) : now();

        [$start, $end, $label] = match ($period) {
            'daily' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay(), $anchor->format('d M Y')],
            'weekly' => [$anchor->copy()->subDays(6)->startOfDay(), $anchor->copy()->endOfDay(), $anchor->copy()->subDays(6)->format('d M').' – '.$anchor->format('d M Y')],
            'monthly' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth(), $anchor->format('F Y')],
            default => abort(404),
        };

        // diffInDays() against endOfDay() (23:59:59.999999) is a hair under
        // a full day, e.g. 0.999999... for the 'daily' case — comparing two
        // exact midnights instead avoids that floating-point epsilon.
        $days = $start->diffInDays($end->copy()->startOfDay()) + 1;
        $activeTechnicianCount = max(1, User::whereHas('role', fn ($q) => $q->where('name', 'lab-technician'))->where('status', true)->count());

        // Bind plain 'Y-m-d' strings, not full Carbon datetimes — the *_date
        // columns store date-only values, and a MySQL DATE column silently
        // coerces a datetime bound value for comparison, but SQLite (used in
        // tests) compares the strings lexicographically, where a bare date
        // like '2026-07-22' sorts before '2026-07-22 00:00:00' and would be
        // wrongly excluded from a whereBetween range.
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $checklists = LabDailyChecklist::whereBetween('checklist_date', [$startDate, $endDate])->get();
        $approvedChecklists = $checklists->where('status', 'approved');

        $expectedEmployeeDays = $activeTechnicianCount * $days;
        $compliance = $expectedEmployeeDays > 0 ? round(($approvedChecklists->count() / $expectedEmployeeDays) * 100, 1) : 0;

        $itemCompliance = $this->itemCompliance($checklists->pluck('id'));

        return [
            'period' => $period,
            'label' => $label,
            'start' => $start,
            'end' => $end,
            'compliance' => $compliance,
            'itemCompliance' => $itemCompliance,
            'checklistsApproved' => $approvedChecklists->count(),
            'checklistsSubmitted' => $checklists->count(),
            'maintenanceCount' => LabEquipmentMaintenanceLog::whereBetween('maintenance_date', [$startDate, $endDate])->count(),
            'overdueMaintenance' => LabEquipmentMaintenanceLog::whereNotNull('next_due_date')->where('next_due_date', '<', today())->count(),
            'mediaCount' => LabMediaStockVerification::whereBetween('verification_date', [$startDate, $endDate])->count(),
            'lowStockMedia' => LabMediaStockVerification::whereBetween('verification_date', [$startDate, $endDate])->whereNotNull('reorder_level')->whereColumn('closing_stock', '<=', 'reorder_level')->count(),
            'chemicalCount' => LabChemicalUsage::whereBetween('usage_date', [$startDate, $endDate])->count(),
            'contaminationCount' => LabContaminationRecord::whereBetween('contamination_date', [$startDate, $endDate])->count(),
            'criticalContamination' => LabContaminationRecord::whereBetween('contamination_date', [$startDate, $endDate])->where('severity', 'critical')->count(),
        ];
    }

    /**
     * % done per catalogue item (see LabDailyChecklist::CHECKLIST_ITEMS)
     * across the given checklist ids, via a single grouped query rather
     * than one query per item.
     */
    private function itemCompliance($checklistIds): array
    {
        $counts = LabChecklistItem::whereIn('lab_daily_checklist_id', $checklistIds)
            ->selectRaw('item_key, COUNT(*) as total, SUM(is_done) as done')
            ->groupBy('item_key')
            ->get()
            ->keyBy('item_key');

        return collect(LabDailyChecklist::CHECKLIST_ITEMS)->map(function ($meta, $key) use ($counts) {
            $row = $counts->get($key);
            $total = (int) ($row->total ?? 0);
            $done = (int) ($row->done ?? 0);

            return $total > 0 ? round(($done / $total) * 100, 1) : 0;
        })->all();
    }
}
