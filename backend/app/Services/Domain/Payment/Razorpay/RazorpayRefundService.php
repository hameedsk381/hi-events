<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Models\RazorpayPayment;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayPaymentRepositoryInterface;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class RazorpayRefundService
{
    public function __construct(
        private readonly RazorpayPaymentRepositoryInterface $razorpayPaymentRepository,
        private readonly RazorpayClientFactory              $clientFactory,
        private readonly OrderRepositoryInterface           $orderRepository,
        private readonly LoggerInterface                    $logger,
    )
    {
    }

    /**
     * @throws RefundNotPossibleException
     * @throws Throwable
     */
    public function refundOrder(int $orderId, ?float $amount = null): void
    {
        /** @var RazorpayPayment $razorpayPayment */
        $razorpayPayment = $this->razorpayPaymentRepository->findFirstWhere([
            'order_id' => $orderId,
        ]);

        if (!$razorpayPayment || !$razorpayPayment->razorpay_payment_id) {
            throw new RefundNotPossibleException(__('No Razorpay payment found for this order.'));
        }

        try {
            $api = $this->clientFactory->createClient();
            $payment = $api->payment->fetch($razorpayPayment->razorpay_payment_id);

            $refundData = [];
            if ($amount !== null) {
                // Assuming amount is in major unit, convert to minor (paise)
                $refundData['amount'] = (int)($amount * 100);
            }

            $refund = $payment->refund($refundData);

            $this->logger->info('Razorpay refund successful', [
                'order_id' => $orderId,
                'refund_id' => $refund->id,
                'amount' => $amount,
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Razorpay refund failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            throw new RefundNotPossibleException(__('Refund failed: ') . $e->getMessage());
        }
    }
}
