<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayRefundService;
use Throwable;

class RazorpayRefundOrderHandler
{
    public function __construct(
        private readonly RazorpayRefundService    $razorpayRefundService,
        private readonly OrderRepositoryInterface $orderRepository,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(RefundOrderDTO $refundOrderDTO): OrderDomainObject
    {
        $this->razorpayRefundService->refundOrder(
            orderId: $refundOrderDTO->orderId,
            amount: $refundOrderDTO->amount,
        );

        return $this->orderRepository->findById($refundOrderDTO->orderId);
    }
}
