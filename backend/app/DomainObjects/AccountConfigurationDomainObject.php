<?php

namespace HiEvents\DomainObjects;

class AccountConfigurationDomainObject extends Generated\AccountConfigurationDomainObjectAbstract
{
    public function getFixedApplicationFee(): float
    {
        return $this->getApplicationFees()['fixed'] ?? config('app.saas_application_fee_fixed');
    }

    public function getPercentageApplicationFee(): float
    {
        return $this->getApplicationFees()['percentage'] ?? config('app.saas_application_fee_percent');
    }
}
