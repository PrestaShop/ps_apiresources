<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Discount;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Discount
{
    public int $discountId;
    public bool $active;
    public bool $isTaxIncluded;
    public bool $highlightInCart;
    public bool $allowPartialUse;
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
    public bool $enabled;
    public bool $taxIncluded;
    public bool $highlightInCart;
    public bool $allowPartialUse;
}

?>
