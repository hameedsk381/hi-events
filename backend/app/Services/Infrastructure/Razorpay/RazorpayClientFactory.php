<?php

namespace HiEvents\Services\Infrastructure\Razorpay;

use Illuminate\Config\Repository;
use Razorpay\Api\Api;

class RazorpayClientFactory
{
    public function __construct(
        private readonly Repository $config
    ) {
    }

    public function createClient(): Api
    {
        return new Api(
            $this->config->get('services.razorpay.key_id'),
            $this->config->get('services.razorpay.key_secret')
        );
    }
}
