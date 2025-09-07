<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PsApiResourcesTest\Integration;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\ApiClient\ApiClientList;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class ApiClientListSerializationTest extends TestCase
{
    public function testDenormalizeWithoutDescription(): void
    {
        $normalizer = new ObjectNormalizer();

        $data = [
            'apiClientId' => 1,
            'clientId' => 'client_id',
            'clientName' => 'Client name',
            // 'description' intentionally missing
            'externalIssuer' => null,
            'enabled' => true,
            'lifetime' => 3600,
        ];

        /** @var ApiClientList $apiClientList */
        $apiClientList = $normalizer->denormalize($data, ApiClientList::class);
        $this->assertNull($apiClientList->description);

        $normalized = $normalizer->normalize($apiClientList);
        $this->assertArrayHasKey('description', $normalized);
        $this->assertNull($normalized['description']);
    }
}
