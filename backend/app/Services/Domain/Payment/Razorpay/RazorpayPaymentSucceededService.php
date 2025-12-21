<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\InvoiceStatus;
use HiEvents\DomainObjects\Status\OrderApplicationFeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use HiEvents\Repository\Interfaces\RazorpayPaymentRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class RazorpayPaymentSucceededService
{
    public function __construct(
        private readonly OrderRepositoryInterface              $orderRepository,
        private readonly DatabaseManager                       $databaseManager,
        private readonly AffiliateRepositoryInterface          $affiliateRepository,
        private readonly InvoiceRepositoryInterface            $invoiceRepository,
        private readonly AttendeeRepositoryInterface           $attendeeRepository,
        private readonly DomainEventDispatcherService          $domainEventDispatcherService,
        private readonly OrderApplicationFeeCalculationService $orderApplicationFeeCalculationService,
        private readonly EventRepositoryInterface              $eventRepository,
        private readonly OrderApplicationFeeService            $orderApplicationFeeService,
        private readonly SendOrderDetailsService               $sendOrderDetailsService,
        private readonly ProductQuantityUpdateService          $productQuantityUpdateService,
        private readonly RazorpayPaymentRepositoryInterface    $razorpayPaymentRepository,
        private readonly RazorpayClientFactory                 $clientFactory,
    )
    {
    }

    /**
     * @throws ResourceConflictException|Throwable
     */
    public function markOrderAsPaid(
        int     $orderId,
        int     $eventId,
        ?string $paymentId = null,
        ?string $signature = null,
        ?string $razorpayOrderId = null,
    ): OrderDomainObject
    {
        return $this->databaseManager->transaction(function () use ($orderId, $eventId, $paymentId, $signature, $razorpayOrderId) {
            /** @var OrderDomainObject $order */
            $order = $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->loadRelation(AttendeeDomainObject::class)
                ->loadRelation(InvoiceDomainObject::class)
                ->findFirstWhere([
                    OrderDomainObjectAbstract::ID => $orderId,
                    OrderDomainObjectAbstract::EVENT_ID => $eventId,
                ]);

            if (!$order) {
                 throw new ResourceConflictException(__('Order not found'));
            }

            $event = $this->eventRepository
                ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
                ->loadRelation(new Relationship(EventSettingDomainObject::class))
                ->findById($order->getEventId());

            if ($order->getStatus() === OrderStatus::COMPLETED->name) {
                return $order;
            }

            $this->updateOrderStatus($orderId);

            $this->updateOrderInvoice($orderId);

            $updatedOrder = $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->findById($orderId);

            // Update affiliate sales if this order has an affiliate
            if ($updatedOrder->getAffiliateId()) {
                $this->affiliateRepository->incrementSales(
                    $updatedOrder->getAffiliateId(),
                    $updatedOrder->getTotalGross()
                );
            }

            $this->updateAttendeeStatuses($updatedOrder);

            $this->productQuantityUpdateService->updateQuantitiesFromOrder($updatedOrder);

            event(new OrderStatusChangedEvent(
                order: $updatedOrder,
                sendEmails: false
            ));

            $this->domainEventDispatcherService->dispatch(
                new OrderEvent(
                    type: DomainEventType::ORDER_CREATED,
                    orderId: $orderId,
                ),
            );

            $this->storeApplicationFeePayment($updatedOrder);

            $this->sendOrderDetailsService->sendCustomerOrderSummary(
                order: $updatedOrder,
                event: $event,
                organizer: $event->getOrganizer(),
                eventSettings: $event->getEventSettings(),
                invoice: $order->getLatestInvoice(),
            );

            $this->updateRazorpayPaymentDetails($orderId, $paymentId, $signature, $razorpayOrderId);

            return $updatedOrder;
        });
    }

    private function updateRazorpayPaymentDetails(int $orderId, ?string $paymentId, ?string $signature, ?string $razorpayOrderId): void
    {
        if (!$paymentId) {
            return;
        }

        $query = ['order_id' => $orderId];

        if ($razorpayOrderId) {
            $query['razorpay_order_id'] = $razorpayOrderId;
        }

        $razorpayPayment = $this->razorpayPaymentRepository->findFirstWhere($query);

        if ($razorpayPayment) {
            $api = $this->clientFactory->createClient();
            $payment = $api->payment->fetch($paymentId);

            $this->razorpayPaymentRepository->updateFromArray($razorpayPayment->id, [
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
                'status' => $payment->status,
                'method' => $payment->method,
            ]);
        }
    }

    private function updateOrderInvoice(int $orderId): void
    {
        $invoice = $this->invoiceRepository->findLatestInvoiceForOrder($orderId);

        if ($invoice) {
            $this->invoiceRepository->updateFromArray($invoice->getId(), [
                'status' => InvoiceStatus::PAID->name,
            ]);
        }
    }

    private function updateOrderStatus(int $orderId): void
    {
        $this->orderRepository->updateFromArray($orderId, [
            OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
            OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_RECEIVED->name,
            OrderDomainObjectAbstract::PAYMENT_PROVIDER => PaymentProviders::RAZORPAY->value,
        ]);
    }

    private function updateAttendeeStatuses(OrderDomainObject $updatedOrder): void
    {
        $this->attendeeRepository->updateWhere(
            attributes: [
                'status' => AttendeeStatus::ACTIVE->name,
            ],
            where: [
                'order_id' => $updatedOrder->getId(),
                'status' => AttendeeStatus::AWAITING_PAYMENT->name,
            ],
        );
    }

    private function storeApplicationFeePayment(OrderDomainObject $updatedOrder): void
    {
        /** @var EventDomainObject $event */
        $event = $this->eventRepository
            ->loadRelation(new Relationship(
                domainObject: AccountDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: AccountConfigurationDomainObject::class,
                        name: 'configuration',
                    ),
                ],
                name: 'account'
            ))
            ->findById($updatedOrder->getEventId());

        /** @var AccountConfigurationDomainObject $config */
        $config = $event->getAccount()->getConfiguration();

        $fee = $this->orderApplicationFeeCalculationService->calculateApplicationFee(
            accountConfiguration: $config,
            order: $updatedOrder,
        );

        if ($fee) {
            $this->orderApplicationFeeService->createOrderApplicationFee(
                orderId: $updatedOrder->getId(),
                applicationFeeAmountMinorUnit: $fee->netApplicationFee->toMinorUnit(),
                orderApplicationFeeStatus: OrderApplicationFeeStatus::PAID,
                paymentMethod: PaymentProviders::RAZORPAY,
                currency: $updatedOrder->getCurrency(),
            );
        }
    }
}
