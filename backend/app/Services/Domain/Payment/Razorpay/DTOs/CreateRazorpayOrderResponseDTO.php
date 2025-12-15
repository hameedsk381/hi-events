<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use HiEvents\DataTransferObjects\BaseDTO;

class CreateRazorpayOrderResponseDTO extends BaseDTO
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $currency,
        public readonly int    $amount,
        public readonly string $keyId,
    )
    {
    }
}
