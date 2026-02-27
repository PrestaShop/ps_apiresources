<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Tax;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;

#[ApiResource]
class AlreadyCorrect
{
    public int $taxId;
    public DecimalNumber $rate;
    public ?DecimalNumber $optionalAmount;
}

?>
-----
<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Tax;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;

#[ApiResource]
class AlreadyCorrect
{
    public int $taxId;
    public DecimalNumber $rate;
    public ?DecimalNumber $optionalAmount;
}

?>
