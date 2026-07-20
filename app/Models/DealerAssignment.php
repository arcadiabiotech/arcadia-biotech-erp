<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DealerAssignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'dealer_id',
        'marketing_user_id',
        'assigned_by',
        'assigned_date',
        'status',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'status' => 'boolean',
    ];

    /**
     * The dealer being managed.
     */
    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    /**
     * The marketing user who manages the dealer.
     */
    public function marketingUser()
    {
        return $this->belongsTo(User::class, 'marketing_user_id');
    }

    /**
     * The admin who created the assignment.
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
