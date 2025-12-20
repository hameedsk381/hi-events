<?php

namespace HiEvents\DomainObjects\Enums;

enum PaymentProviders: string
{
    use BaseEnum;

    case OFFLINE = 'OFFLINE';
    case RAZORPAY = 'RAZORPAY';
}
