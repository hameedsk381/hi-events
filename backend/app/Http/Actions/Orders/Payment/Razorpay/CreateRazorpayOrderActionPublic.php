<?php

namespace HiEvents\Http\Actions\Orders\Payment\Razorpay;

use HiEvents\Exceptions\Razorpay\CreateRazorpayOrderFailedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\CreateRazorpayOrderHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreateRazorpayOrderActionPublic extends BaseAction
{
    public function __construct(
        private readonly CreateRazorpayOrderHandler $createOrderHandler,
    )
    {
    }

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        try {
            $response = $this->createOrderHandler->handle($orderShortId);
        } catch (CreateRazorpayOrderFailedException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonResponse([
            'order_id' => $response->orderId,
            'key_id' => $response->keyId,
            'amount' => $response->amount,
            'currency' => $response->currency,
        ]);
    }
}
