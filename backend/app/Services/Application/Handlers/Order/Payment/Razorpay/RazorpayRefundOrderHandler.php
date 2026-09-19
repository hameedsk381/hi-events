<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Order\OrderRefunded;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayRefundService;
use HiEvents\Values\MoneyValue;
use Illuminate\Contracts\Mail\Mailer;
use Throwable;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class RazorpayRefundOrderHandler
{
    public function __construct(
        private readonly RazorpayRefundService    $razorpayRefundService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderCancelService       $orderCancelService,
        private readonly Mailer                   $mailer,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(RefundOrderDTO $refundOrderDTO): OrderDomainObject
    {
        $order = $this->orderRepository->findFirstWhere([
            OrderDomainObjectAbstract::ID => $refundOrderDTO->orderId,
            OrderDomainObjectAbstract::EVENT_ID => $refundOrderDTO->event_id,
        ]);

        if (!$order) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        $this->razorpayRefundService->refundOrder(
            orderId: $refundOrderDTO->orderId,
            amount: $refundOrderDTO->amount,
        );

        $updatedOrder = $this->orderRepository->findById($refundOrderDTO->orderId);

        if ($refundOrderDTO->cancel_order) {
            $this->orderCancelService->cancelOrder($updatedOrder);
            $updatedOrder = $this->orderRepository->findById($refundOrderDTO->orderId);
        }

        if ($refundOrderDTO->notify_buyer) {
            $event = $this->eventRepository
                ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
                ->loadRelation(EventSettingDomainObject::class)
                ->findById($updatedOrder->getEventId());

            $this->mailer
                ->to($updatedOrder->getEmail())
                ->locale($updatedOrder->getLocale())
                ->send(new OrderRefunded(
                    order: $updatedOrder,
                    event: $event,
                    organizer: $event->getOrganizer(),
                    eventSettings: $event->getEventSettings(),
                    refundAmount: MoneyValue::fromFloat($refundOrderDTO->amount, $updatedOrder->getCurrency()),
                ));
        }

        return $updatedOrder;
    }
}
