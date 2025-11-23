<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\TaxRulesGroup;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/tax-rules-group/{taxRulesGroupId}',
        ),
        new CQRSCreate(
            uriTemplate: '/tax-rules-group',
        ),
    ]
)]
class TaxRulesGroup
{
}

?>
-----
<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\TaxRulesGroup;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/tax-rules-groups/{taxRulesGroupId}',
        ),
        new CQRSCreate(
            uriTemplate: '/tax-rules-groups',
        ),
    ]
)]
class TaxRulesGroup
{
}

?>
