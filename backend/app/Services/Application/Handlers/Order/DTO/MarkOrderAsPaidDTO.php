<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class MarkOrderAsPaidDTO extends BaseDTO
{
    public function __construct(
        public readonly int     $eventId,
        public readonly int     $orderId,
        public readonly ?string $paymentId = null,
        public readonly ?string $signature = null,
    )
    {
    }
}
