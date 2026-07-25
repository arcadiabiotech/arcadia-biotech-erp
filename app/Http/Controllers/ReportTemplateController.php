<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportTemplateStoreRequest;
use App\Http\Requests\ReportTemplateUpdateRequest;
use App\Models\LabChemicalUsage;
use App\Models\LabContaminationRecord;
use App\Models\LabDailyChecklist;
use App\Models\LabEquipmentMaintenanceLog;
use App\Models\LabMediaStockVerification;
use App\Models\ReportTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportTemplateController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ReportTemplate::class, 'report_template');
    }

    public function index()
    {
        $templates = ReportTemplate::with('createdBy')->latest()->get();

        return view('report-templates.index', compact('templates'));
    }

    public function create()
    {
        return view('report-templates.form', [
            'reportTemplate' => new ReportTemplate(['date_range_type' => 'monthly']),
        ]);
    }

    public function store(ReportTemplateStoreRequest $request)
    {
        ReportTemplate::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('report-templates.index')->with('success', 'Report template created.');
    }

    public function edit(ReportTemplate $reportTemplate)
    {
        return view('report-templates.form', compact('reportTemplate'));
    }

    public function update(ReportTemplateUpdateRequest $request, ReportTemplate $reportTemplate)
    {
        $reportTemplate->update($request->validated());

        return redirect()->route('report-templates.index')->with('success', 'Report template updated.');
    }

    public function destroy(ReportTemplate $reportTemplate)
    {
        $reportTemplate->delete();

        return redirect()->route('report-templates.index')->with('success', 'Report template deleted.');
    }

    /**
     * Templates aren't stored snapshots — running one just re-runs the
     * aggregation for the template's saved module selection and date-range
     * type against whatever data exists right now, same as lab-reports.show
     * but scoped to a chosen subset of modules instead of all five.
     */
    public function run(Request $request, ReportTemplate $reportTemplate)
    {
        $this->authorize('view', $reportTemplate);

        [$start, $end, $label] = $this->periodBounds($reportTemplate->date_range_type, $request->date('date'));
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $metrics = collect($reportTemplate->modules)
            ->mapWithKeys(fn (string $module) => [$module => $this->moduleMetrics($module, $startDate, $endDate)]);

        return view('report-templates.run', [
            'reportTemplate' => $reportTemplate,
            'label' => $label,
            'metrics' => $metrics,
        ]);
    }

    private function periodBounds(string $period, ?Carbon $anchor): array
    {
        $anchor = $anchor ? Carbon::parse($anchor) : now();

        return match ($period) {
            'daily' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay(), $anchor->format('d M Y')],
            'weekly' => [$anchor->copy()->subDays(6)->startOfDay(), $anchor->copy()->endOfDay(), $anchor->copy()->subDays(6)->format('d M').' – '.$anchor->format('d M Y')],
            'monthly' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth(), $anchor->format('F Y')],
        };
    }

    private function moduleMetrics(string $module, string $startDate, string $endDate): array
    {
        return match ($module) {
            'lab-checklists' => [
                'label' => ReportTemplate::MODULES['lab-checklists'],
                'Submitted' => LabDailyChecklist::whereBetween('checklist_date', [$startDate, $endDate])->count(),
                'Approved' => LabDailyChecklist::whereBetween('checklist_date', [$startDate, $endDate])->where('status', 'approved')->count(),
            ],
            'lab-maintenance' => [
                'label' => ReportTemplate::MODULES['lab-maintenance'],
                'Logs in period' => LabEquipmentMaintenanceLog::whereBetween('maintenance_date', [$startDate, $endDate])->count(),
                'Overdue' => LabEquipmentMaintenanceLog::whereNotNull('next_due_date')->where('next_due_date', '<', today())->count(),
            ],
            'lab-media' => [
                'label' => ReportTemplate::MODULES['lab-media'],
                'Verifications in period' => LabMediaStockVerification::whereBetween('verification_date', [$startDate, $endDate])->count(),
                'Low stock items' => LabMediaStockVerification::whereBetween('verification_date', [$startDate, $endDate])->whereNotNull('reorder_level')->whereColumn('closing_stock', '<=', 'reorder_level')->count(),
            ],
            'lab-chemicals' => [
                'label' => ReportTemplate::MODULES['lab-chemicals'],
                'Usage entries' => LabChemicalUsage::whereBetween('usage_date', [$startDate, $endDate])->count(),
            ],
            'lab-contamination' => [
                'label' => ReportTemplate::MODULES['lab-contamination'],
                'Incidents' => LabContaminationRecord::whereBetween('contamination_date', [$startDate, $endDate])->count(),
                'Critical' => LabContaminationRecord::whereBetween('contamination_date', [$startDate, $endDate])->where('severity', 'critical')->count(),
            ],
        };
    }
}
