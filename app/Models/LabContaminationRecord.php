<?php

namespace App\Models;

use App\Models\Concerns\HasSupervisorApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabContaminationRecord extends Model
{
    use HasFactory, SoftDeletes, HasSupervisorApproval;

    public const STATUSES = ['draft', 'pending', 'approved', 'rejected'];

    public const TYPES = ['fungal', 'bacterial', 'yeast', 'unknown'];

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public const APPROVAL_MODULE = 'lab-contamination';

    protected $fillable = [
        'contamination_no',
        'reported_by',
        'contamination_date',
        'culture_batch_number',
        'contamination_type',
        'severity',
        'affected_qty',
        'root_cause',
        'corrective_action',
        'photo',
        'remarks',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'contamination_date' => 'date:Y-m-d',
        'affected_qty' => 'integer',
    ];

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
