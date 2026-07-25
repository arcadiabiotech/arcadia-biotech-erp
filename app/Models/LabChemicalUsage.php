<?php

namespace App\Models;

use App\Models\Concerns\HasSupervisorApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabChemicalUsage extends Model
{
    use HasFactory, SoftDeletes, HasSupervisorApproval;

    public const STATUSES = ['draft', 'pending', 'approved', 'rejected'];

    public const APPROVAL_MODULE = 'lab-chemicals';

    protected $fillable = [
        'usage_no',
        'used_by',
        'usage_date',
        'chemical_name',
        'purpose',
        'quantity_used',
        'unit',
        'batch_number',
        'photo',
        'remarks',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'usage_date' => 'date:Y-m-d',
        'quantity_used' => 'decimal:2',
    ];

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by');
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
