<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Discount;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Discount
{
    public int $discountId;
    public bool $isTaxIncluded;
    public string $name;
}

?>
-----
<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Discount;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Discount
{
    public int $discountId;
    public bool $taxIncluded;
    public string $name;
}

?>
