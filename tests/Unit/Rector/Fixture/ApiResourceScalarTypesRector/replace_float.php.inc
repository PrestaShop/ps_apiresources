<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Tax;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Tax
{
    public int $taxId;
    public float $rate;
}

?>
-----
<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Tax;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Tax
{
    public int $taxId;
    public \PrestaShop\Decimal\DecimalNumber $rate;
}

?>
