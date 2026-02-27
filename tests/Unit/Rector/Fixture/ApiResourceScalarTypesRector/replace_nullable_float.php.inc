<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Customer
{
    public int $customerId;
    public ?float $allowedOutstandingAmount;
}

?>
-----
<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Customer
{
    public int $customerId;
    public ?\PrestaShop\Decimal\DecimalNumber $allowedOutstandingAmount;
}

?>
