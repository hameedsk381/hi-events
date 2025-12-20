<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RazorpayPayment extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'amount',
        'currency',
        'status',
        'method',
        'error_details',
    ];

    protected $casts = [
        'error_details' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
