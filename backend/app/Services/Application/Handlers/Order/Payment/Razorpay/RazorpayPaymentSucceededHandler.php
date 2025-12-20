<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Services\Application\Handlers\Order\DTO\MarkOrderAsPaidDTO;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentSucceededService;
use Psr\Log\LoggerInterface;
use Throwable;

class RazorpayPaymentSucceededHandler
{
    public function __construct(
        private readonly RazorpayPaymentSucceededService $razorpayPaymentSucceededService,
        private readonly LoggerInterface                 $logger,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(MarkOrderAsPaidDTO $dto): OrderDomainObject
    {
        $this->logger->info(__('Handling successful Razorpay payment'), [
            'orderId' => $dto->orderId,
            'eventId' => $dto->eventId,
        ]);

        return $this->razorpayPaymentSucceededService->markOrderAsPaid(
            $dto->orderId,
            $dto->eventId,
            $dto->paymentId,
            $dto->signature,
        );
    }
}
