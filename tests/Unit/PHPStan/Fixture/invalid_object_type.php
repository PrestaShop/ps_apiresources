<?php
declare(strict_types=1);

// A forbidden object type defined in this file so PHPStan can resolve it.
namespace Some\Forbidden {
    class ValueObject
    {
    }
}

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Stub {
    use ApiPlatform\Metadata\ApiResource;
    use Some\Forbidden\ValueObject;

    #[ApiResource]
    class InvalidObjectTypeResource
    {
        public int $id;
        public ValueObject $something;
    }
}
