<?php
declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Stub;

// No #[ApiResource] attribute — the rule must skip this class entirely,
// even though the namespace matches and forbidden types are present.
class NotAnApiResource
{
    public int $id;
    public float $rate;
    public $name;
}
