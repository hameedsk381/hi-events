<?php

namespace HiEvents\Http\Actions\Orders\Payment\Razorpay;

use HiEvents\Exceptions\Razorpay\CreateRazorpayOrderFailedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
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

        $order = $this->orderRepository->findByShortId($orderShortId);

        if (!$order) {
            return $this->errorResponse(__('Order not found'), Response::HTTP_NOT_FOUND);
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
            ));

        } catch (\Exception $e) {
             return $this->errorResponse(__('Payment verification failed'), Response::HTTP_BAD_REQUEST);
        }

        return $this->jsonResponse(['status' => 'success']);
    }
}
