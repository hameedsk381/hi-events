<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\Models\RazorpayPayment;
use HiEvents\Repository\Interfaces\RazorpayPaymentRepositoryInterface;

class RazorpayPaymentRepository extends BaseRepository implements RazorpayPaymentRepositoryInterface
{
    protected function getModel(): string
    {
        return RazorpayPayment::class;
    }

    public function getDomainObject(): string
    {
        // For now, we use the model directly as it's a simple mapping.
        // If needed, we can create RazorpayPaymentDomainObject.
        return RazorpayPayment::class;
    }
}
