<?php

namespace App\Models;

use App\Models\Concerns\HasSupervisorApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabMediaStockVerification extends Model
{
    use HasFactory, SoftDeletes, HasSupervisorApproval;

    public const STATUSES = ['draft', 'pending', 'approved', 'rejected'];

    public const APPROVAL_MODULE = 'lab-media';

    protected $fillable = [
        'verification_no',
        'verified_by',
        'verification_date',
        'media_name',
        'unit',
        'opening_stock',
        'received_qty',
        'consumed_qty',
        'closing_stock',
        'reorder_level',
        'photo',
        'remarks',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'verification_date' => 'date:Y-m-d',
        'opening_stock' => 'decimal:2',
        'received_qty' => 'decimal:2',
        'consumed_qty' => 'decimal:2',
        'closing_stock' => 'decimal:2',
        'reorder_level' => 'decimal:2',
    ];

    public function getIsLowStockAttribute(): bool
    {
        return $this->reorder_level !== null && $this->closing_stock <= $this->reorder_level;
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
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
