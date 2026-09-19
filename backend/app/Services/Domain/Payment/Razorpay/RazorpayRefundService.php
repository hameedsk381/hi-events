<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\Models\RazorpayPayment;
use HiEvents\Models\Order;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayPaymentRepositoryInterface;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsRefundService;
use Illuminate\Database\DatabaseManager;
use HiEvents\Values\MoneyValue;
use Psr\Log\LoggerInterface;
use Throwable;

class RazorpayRefundService
{
    public function __construct(
        private readonly RazorpayPaymentRepositoryInterface $razorpayPaymentRepository,
        private readonly RazorpayClientFactory              $clientFactory,
        private readonly OrderRepositoryInterface           $orderRepository,
        private readonly OrderRefundRepositoryInterface    $orderRefundRepository,
        private readonly DatabaseManager                    $databaseManager,
        private readonly EventStatisticsRefundService       $eventStatisticsRefundService,
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
        $this->databaseManager->transaction(function () use ($orderId, $amount): void {
            Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $order = $this->orderRepository->findById($orderId);

            $remaining = $order->getTotalGross() - $order->getTotalRefunded();
            if ($remaining <= 0 || $amount !== null && ($amount <= 0 || $amount > $remaining)) {
                throw new RefundNotPossibleException(__('Refund amount exceeds the remaining refundable balance.'));
            }

            $refundAmount = $amount ?? $remaining;
            $money = MoneyValue::fromFloat($refundAmount, $order->getCurrency());

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
                $refund = $payment->refund(['amount' => $money->toMinorUnit()]);
                $newTotalRefunded = $order->getTotalRefunded() + $refundAmount;
                $refundStatus = $newTotalRefunded >= $order->getTotalGross()
                    ? OrderRefundStatus::REFUNDED->name
                    : OrderRefundStatus::PARTIALLY_REFUNDED->name;

                $this->orderRefundRepository->create([
                    'order_id' => $orderId,
                    'payment_provider' => PaymentProviders::RAZORPAY->value,
                    'refund_id' => $refund->id,
                    'amount' => $refundAmount,
                    'currency' => $order->getCurrency(),
                    'status' => $refundStatus,
                ]);

                $this->orderRepository->updateFromArray($orderId, [
                    'total_refunded' => $newTotalRefunded,
                    'refund_status' => $refundStatus,
                ]);
                try {
                    $this->eventStatisticsRefundService->updateForRefund($order, $money);
                } catch (Throwable $statisticsException) {
                    $this->logger->error('Razorpay refund statistics update failed', [
                        'order_id' => $orderId,
                        'error' => $statisticsException->getMessage(),
                    ]);
                }

                $this->logger->info('Razorpay refund successful', [
                    'order_id' => $orderId,
                    'refund_id' => $refund->id,
                    'amount' => $refundAmount,
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Razorpay refund failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);

                throw new RefundNotPossibleException(__('Refund failed. Please try again later.'));
            }
        });
    }
}
