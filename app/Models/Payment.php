<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    public const PAYMENT_MODES = ['Cash', 'Cheque', 'RTGS', 'NEFT', 'IMPS', 'UPI', 'Bank Transfer'];

    protected $fillable = [
        'payment_no',
        'invoice_id',
        'dealer_id',
        'farmer_id',
        'payment_date',
        'payment_mode',
        'reference_no',
        'bank_name',
        'amount',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
