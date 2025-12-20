<?php

namespace Tests\Unit\Services\Domain\Payment\Razorpay;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\Razorpay\CreateRazorpayOrderFailedException;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderRequestDTO;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayOrderCreationService;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;
use Psr\Log\LoggerInterface;
use Razorpay\Api\Api;
use Tests\TestCase;

class RazorpayOrderCreationServiceTest extends TestCase
{
    private LoggerInterface $logger;
    private RazorpayClientFactory $clientFactory;
    private Repository $config;
    private RazorpayOrderCreationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->clientFactory = $this->createMock(RazorpayClientFactory::class);
        $this->config = $this->createMock(Repository::class);

        $this->service = new RazorpayOrderCreationService(
            $this->logger,
            $this->clientFactory,
            $this->config
        );
    }

    public function test_create_order_successfully(): void
    {
        $order = $this->createMock(OrderDomainObject::class);
        $order->method('getShortId')->willReturn('ORD-123');
        $order->method('getId')->willReturn(1);
        $order->method('getEventId')->willReturn(10);

        $money = MoneyValue::fromFloat(100.00, 'INR');

        $dto = new CreateRazorpayOrderRequestDTO(
            amount: $money,
            currencyCode: 'INR',
            account: $this->createMock(\HiEvents\DomainObjects\AccountDomainObject::class),
            order: $order
        );

        $razorpayApi = $this->createMock(Api::class);
        $orderResource = $this->createMock(\Razorpay\Api\Order::class);

        // Razorpay API uses magic properties
        $razorpayApi->order = $orderResource;

        $this->clientFactory->method('createClient')->willReturn($razorpayApi);
        $this->config->method('get')->with('services.razorpay.key_id')->willReturn('rzp_test_123');

        $orderResource->method('create')->with([
            'receipt' => 'ORD-123',
            'amount' => 10000,
            'currency' => 'INR',
            'notes' => [
                'order_id' => 1,
                'event_id' => 10,
                'order_short_id' => 'ORD-123',
            ]
        ])->willReturn((object)[
            'id' => 'order_K9v1x5x1x5x1x5'
        ]);

        $response = $this->service->createOrder($dto);

        $this->assertEquals('order_K9v1x5x1x5x1x5', $response->orderId);
        $this->assertEquals('INR', $response->currency);
        $this->assertEquals(10000, $response->amount);
        $this->assertEquals('rzp_test_123', $response->keyId);
    }

    public function test_create_order_throws_exception_on_failure(): void
    {
        $order = $this->createMock(OrderDomainObject::class);
        $order->method('getShortId')->willReturn('ORD-123');

        $dto = new CreateRazorpayOrderRequestDTO(
            amount: MoneyValue::fromFloat(100.00, 'INR'),
            currencyCode: 'INR',
            account: $this->createMock(\HiEvents\DomainObjects\AccountDomainObject::class),
            order: $order
        );

        $this->clientFactory->method('createClient')->willThrowException(new \Exception('Connection failed'));

        $this->logger->expects($this->once())->method('error');

        $this->expectException(CreateRazorpayOrderFailedException::class);

        $this->service->createOrder($dto);
    }
}
