<?php

namespace HiEvents\DomainObjects;

class RazorpayPaymentDomainObject extends AbstractDomainObject
{
    public int $id;
    public int $orderId;
    public string $razorpayOrderId;
    public ?string $razorpayPaymentId = null;
    public ?string $razorpaySignature = null;
    public ?int $amount = null;
    public ?string $currency = null;
    public ?string $status = null;
    public ?string $method = null;
    public ?array $errorDetails = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setOrderId(int $orderId): self
    {
        $this->orderId = $orderId;
        return $this;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function setRazorpayOrderId(string $razorpayOrderId): self
    {
        $this->razorpayOrderId = $razorpayOrderId;
        return $this;
    }

    public function getRazorpayOrderId(): string
    {
        return $this->razorpayOrderId;
    }

    public function setRazorpayPaymentId(?string $razorpayPaymentId): self
    {
        $this->razorpayPaymentId = $razorpayPaymentId;
        return $this;
    }

    public function getRazorpayPaymentId(): ?string
    {
        return $this->razorpayPaymentId;
    }

    public function setRazorpaySignature(?string $razorpaySignature): self
    {
        $this->razorpaySignature = $razorpaySignature;
        return $this;
    }

    public function getRazorpaySignature(): ?string
    {
        return $this->razorpaySignature;
    }

    public function setAmount(?int $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setCurrency(?string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setMethod(?string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setErrorDetails(?array $errorDetails): self
    {
        $this->errorDetails = $errorDetails;
        return $this;
    }

    public function getErrorDetails(): ?array
    {
        return $this->errorDetails;
    }

    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'razorpay_order_id' => $this->razorpayOrderId,
            'razorpay_payment_id' => $this->razorpayPaymentId,
            'razorpay_signature' => $this->razorpaySignature,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'method' => $this->method,
            'error_details' => $this->errorDetails,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
