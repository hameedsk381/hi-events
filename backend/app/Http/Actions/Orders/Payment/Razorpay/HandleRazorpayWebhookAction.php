<?php

namespace HiEvents\Http\Actions\Orders\Payment\Razorpay;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayPaymentRepositoryInterface;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentSucceededService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use RuntimeException;

class HandleRazorpayWebhookAction extends BaseAction
{
    public function __construct(
        private readonly RazorpayPaymentSucceededService $paymentSucceededService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RazorpayPaymentRepositoryInterface $razorpayPaymentRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $webhookSecret = config('services.razorpay.webhook_secret');
        if (empty($webhookSecret)) {
            $webhookSecret = env('RAZORPAY_WEBHOOK_SECRET');
        }

        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        if (empty($webhookSecret)) {
            Log::error('Razorpay webhook secret is not set');
            return $this->errorResponse('Webhook secret not set', 500);
        }

        if (empty($signature)) {
            Log::error('Razorpay webhook signature missing');
            return $this->errorResponse('Signature missing', 400);
        }

        try {
            $api = new Api(config('services.razorpay.key_id'), config('services.razorpay.key_secret'));
            $api->utility->verifyWebhookSignature($payload, $signature, $webhookSecret);
        } catch (SignatureVerificationError $e) {
            Log::error('Razorpay webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Signature verification failed', 400);
        }

        $data = json_decode($payload, true);
        $event = $data['event'] ?? null;

        Log::info('Received Razorpay webhook', ['event' => $event]);

        if ($event === 'order.paid' || $event === 'payment.captured') {
            $this->handleOrderPaid($data);
        }

        return $this->jsonResponse(['status' => 'ok']);
    }

    private function handleOrderPaid(array $data): void
    {
        $paymentEntity = $data['payload']['payment']['entity'] ?? null;
        $orderEntity = $data['payload']['order']['entity'] ?? null;

        $razorpayOrderId = $orderEntity['id'] ?? $paymentEntity['order_id'] ?? null;

        if (!$razorpayOrderId) {
            Log::error('Razorpay order id missing in webhook payload');
            return;
        }

        $razorpayPayment = $this->razorpayPaymentRepository->findFirstByField('razorpay_order_id', $razorpayOrderId);

        if (!$razorpayPayment) {
            Log::warning('Razorpay payment record not found for webhook order id', ['razorpay_order_id' => $razorpayOrderId]);
            return;
        }

        $providerAmount = $paymentEntity['amount'] ?? $orderEntity['amount'] ?? null;
        $providerCurrency = $paymentEntity['currency'] ?? $orderEntity['currency'] ?? null;
        $providerStatus = $paymentEntity['status'] ?? $orderEntity['status'] ?? null;

        if ($providerAmount === null || (int)$providerAmount !== $razorpayPayment->getAmount()) {
            throw new RuntimeException('Razorpay webhook amount does not match the local payment.');
        }

        if (!$providerCurrency || strcasecmp($providerCurrency, (string)$razorpayPayment->getCurrency()) !== 0) {
            throw new RuntimeException('Razorpay webhook currency does not match the local payment.');
        }

        if ($eventStatus = $data['event'] ?? null) {
            $validStatus = $eventStatus === 'payment.captured'
                ? $providerStatus === 'captured'
                : in_array($providerStatus, ['paid', 'captured'], true);

            if (!$validStatus) {
                throw new RuntimeException('Razorpay webhook payment is not in a payable status.');
            }
        }

        $orderId = $razorpayPayment->getOrderId();
        $order = $this->orderRepository->findById($orderId);

        if (!$order) {
            Log::error('Order not found for Razorpay webhook', ['order_id' => $orderId]);
            return;
        }

        try {
            $this->paymentSucceededService->markOrderAsPaid(
                orderId: $orderId,
                eventId: $order->getEventId(),
                paymentId: $paymentEntity['id'] ?? null,
                signature: null,
                razorpayOrderId: $razorpayOrderId
            );
            Log::info('Order marked as paid via Razorpay webhook', ['order_id' => $orderId]);
        } catch (\Exception $e) {
            Log::error('Failed to mark order as paid via webhook', ['error' => $e->getMessage(), 'order_id' => $orderId]);
            throw $e;
        }
    }
}
