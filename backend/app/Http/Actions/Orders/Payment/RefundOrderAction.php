<?php

namespace HiEvents\Http\Actions\Orders\Payment;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Order\RefundOrderRequest;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\RazorpayRefundOrderHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundOrderAction extends BaseAction
{
    public function __construct(private readonly RazorpayRefundOrderHandler $refundOrderHandler)
    {
    }

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function __invoke(RefundOrderRequest $request, int $eventId, int $orderId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $order = $this->refundOrderHandler->handle(
                refundOrderDTO: RefundOrderDTO::fromArray(array_merge($request->validated(), [
                    'event_id' => $eventId,
                    'order_id' => $orderId,
                ]))
            );
        } catch (RefundNotPossibleException|Throwable $exception) {
            throw ValidationException::withMessages([
                'amount' => $exception->getMessage(),
            ]);
        }

        return $this->resourceResponse(OrderResource::class, $order);
    }
}
