<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Feature;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/features/value/{featureValueId}',
        ),
        new CQRSCreate(
            uriTemplate: '/features/value',
        ),
    ]
)]
class FeatureValue
{
}

?>
-----
<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Feature;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/features/values/{featureValueId}',
        ),
        new CQRSCreate(
            uriTemplate: '/features/values',
        ),
    ]
)]
class FeatureValue
{
}

?>
