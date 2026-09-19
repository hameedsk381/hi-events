<?php

namespace HiEvents\Http\Actions\Orders\Payment\Razorpay;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayPaymentRepositoryInterface;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use HiEvents\Services\Application\Handlers\Order\DTO\MarkOrderAsPaidDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\RazorpayPaymentSucceededHandler;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use Illuminate\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use Symfony\Component\HttpFoundation\Response;

class VerifyRazorpayPaymentActionPublic extends BaseAction
{
    public function __construct(
        private readonly OrderRepositoryInterface        $orderRepository,
        private readonly RazorpayPaymentSucceededHandler $markOrderAsPaidHandler,
        private readonly RazorpayClientFactory           $clientFactory,
        private readonly Repository                      $config,
        private readonly RazorpayPaymentRepositoryInterface $razorpayPaymentRepository,
        private readonly CheckoutSessionManagementService $sessionService,
    )
    {
    }

    public function __invoke(int $eventId, string $orderShortId, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $order = $this->orderRepository->findFirstWhere([
            'short_id' => $orderShortId,
            'event_id' => $eventId,
        ]);

        if (!$order) {
            return $this->errorResponse(__('Order not found'), Response::HTTP_NOT_FOUND);
        }

        if (!$this->sessionService->verifySession($order->getSessionId())) {
            return $this->errorResponse(__('Sorry, we could not verify your session.'), Response::HTTP_UNAUTHORIZED);
        }

        $payment = $this->razorpayPaymentRepository->findFirstWhere([
            'order_id' => $order->getId(),
            'razorpay_order_id' => $payload['razorpay_order_id'],
        ]);

        if (!$payment) {
            return $this->errorResponse(__('Payment does not belong to this order.'), Response::HTTP_BAD_REQUEST);
        }

        try {
            $api = $this->clientFactory->createClient();
            $attributes = [
                'razorpay_order_id' => $payload['razorpay_order_id'],
                'razorpay_payment_id' => $payload['razorpay_payment_id'],
                'razorpay_signature' => $payload['razorpay_signature']
            ];

            $api->utility->verifyPaymentSignature($attributes);

            // Mark as paid
            $this->markOrderAsPaidHandler->handle(new MarkOrderAsPaidDTO(
                eventId: $eventId,
                orderId: $order->getId(),
                paymentId: $payload['razorpay_payment_id'],
                signature: $payload['razorpay_signature'],
                razorpayOrderId: $payload['razorpay_order_id'],
            ));

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Razorpay payment verification failed', [
                'error' => $e->getMessage(),
                 'order_short_id' => $orderShortId,
                 'razorpay_order_id' => $payload['razorpay_order_id'],
                 'razorpay_payment_id' => $payload['razorpay_payment_id'],
            ]);

             return $this->errorResponse(__('Payment verification failed'), Response::HTTP_BAD_REQUEST);
        }

        return $this->jsonResponse(['status' => 'success']);
    }
}
