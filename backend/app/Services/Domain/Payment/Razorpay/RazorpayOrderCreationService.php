<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\Exceptions\Razorpay\CreateRazorpayOrderFailedException;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderRequestDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderResponseDTO;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use HiEvents\Repository\Interfaces\RazorpayPaymentRepositoryInterface;
use Illuminate\Config\Repository;
use Psr\Log\LoggerInterface;
use Razorpay\Api\Api;
use Throwable;

class RazorpayOrderCreationService
{
    public function __construct(
        private readonly LoggerInterface                    $logger,
        private readonly RazorpayClientFactory              $clientFactory,
        private readonly Repository                         $config,
        private readonly RazorpayPaymentRepositoryInterface $razorpayPaymentRepository,
    )
    {
    }

    /**
     * @throws CreateRazorpayOrderFailedException
     */
    public function createOrder(CreateRazorpayOrderRequestDTO $dto): CreateRazorpayOrderResponseDTO
    {
        try {
            $client = $this->clientFactory->createClient();

            $orderData = [
                'receipt' => $dto->order->getShortId(),
                'amount' => $dto->amount->toMinorUnit(),
                'currency' => $dto->currencyCode,
                'notes' => [
                    'order_id' => $dto->order->getId(),
                    'event_id' => $dto->order->getEventId(),
                    'order_short_id' => $dto->order->getShortId(),
                ]
            ];

            $order = $client->order->create($orderData);

            $this->razorpayPaymentRepository->create([
                'order_id' => $dto->order->getId(),
                'razorpay_order_id' => $order->id,
                'amount' => $orderData['amount'],
                'currency' => $orderData['currency'],
                'status' => $order->status,
                'method' => 'unknown', // Default value as method is not available in order creation response
            ]);

            $this->logger->info('Razorpay order created', [
                'razorpay_order_id' => $order->id,
                'order_short_id' => $dto->order->getShortId(),
            ]);

            return new CreateRazorpayOrderResponseDTO(
                orderId: $order->id,
                currency: $orderData['currency'],
                amount: $orderData['amount'],
                keyId: $this->config->get('services.razorpay.key_id')
            );
        } catch (Throwable $exception) {
            $this->logger->error("Razorpay order creation failed: {$exception->getMessage()}", [
                'exception' => $exception,
                'order_short_id' => $dto->order->getShortId(),
            ]);

            throw new CreateRazorpayOrderFailedException(
                __('There was an error communicating with the payment provider. Please try again later.')
            );
        }
    }
}
