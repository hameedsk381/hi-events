<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\Razorpay\CreateRazorpayOrderFailedException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayPaymentRepositoryInterface;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderRequestDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderResponseDTO;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayOrderCreationService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use HiEvents\Values\MoneyValue;
use HiEvents\Models\Order;
use Illuminate\Database\DatabaseManager;
use Throwable;

readonly class CreateRazorpayOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface           $orderRepository,
        private RazorpayOrderCreationService       $razorpayOrderService,
        private CheckoutSessionManagementService   $sessionIdentifierService,
        private AccountRepositoryInterface         $accountRepository,
        private RazorpayPaymentRepositoryInterface $razorpayPaymentRepository,
        private DatabaseManager                    $databaseManager,
    )
    {
    }

    /**
     * @throws CreateRazorpayOrderFailedException
     * @throws Throwable
     */
    public function handle(int $eventId, string $orderShortId): CreateRazorpayOrderResponseDTO
    {
        $order = $this->orderRepository->findFirstWhere([
            'short_id' => $orderShortId,
            'event_id' => $eventId,
        ]);

        if (!$order || !$this->sessionIdentifierService->verifySession($order->getSessionId())) {
            throw new UnauthorizedException(__('Sorry, we could not verify your session. Please create a new order.'));
        }

        if ($order->getStatus() !== OrderStatus::RESERVED->name || $order->isReservedOrderExpired()) {
            throw new ResourceConflictException(__('Sorry, is expired or not in a valid state.'));
        }

        return $this->databaseManager->transaction(function () use ($order): CreateRazorpayOrderResponseDTO {
            Order::query()->whereKey($order->getId())->lockForUpdate()->firstOrFail();

            $existingPayment = $this->razorpayPaymentRepository->findFirstWhere([
                'order_id' => $order->getId(),
            ]);

            if ($existingPayment && $existingPayment->getRazorpayOrderId()) {
                return new CreateRazorpayOrderResponseDTO(
                    orderId: $existingPayment->getRazorpayOrderId(),
                    currency: $existingPayment->getCurrency() ?? $order->getCurrency(),
                    amount: $existingPayment->getAmount() ?? MoneyValue::fromFloat($order->getTotalGross(), $order->getCurrency())->toMinorUnit(),
                    keyId: config('services.razorpay.key_id'),
                );
            }

            $account = $this->accountRepository
                ->loadRelation(new Relationship(
                    domainObject: AccountConfigurationDomainObject::class,
                    name: 'configuration',
                ))
                ->loadRelation(new Relationship(
                    domainObject: AccountVatSettingDomainObject::class,
                    name: 'account_vat_setting',
                ))
                ->findByEventId($order->getEventId());

            return $this->razorpayOrderService->createOrder(
                CreateRazorpayOrderRequestDTO::fromArray([
                    'amount' => MoneyValue::fromFloat($order->getTotalGross(), $order->getCurrency()),
                    'currencyCode' => $order->getCurrency(),
                    'account' => $account,
                    'order' => $order,
                    'vatSettings' => $account->getAccountVatSetting(),
                ])
            );
        });
    }
}
