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

namespace PsApiResourcesTest\Integration\ApiPlatform;

class DiscountTypeEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/discount-types',
        ];
    }

    public function testGetDiscountTypes(): void
    {
        $discountTypes = $this->getItem('/discount-types', ['discount_read']);
        $this->assertIsArray($discountTypes);
        $this->assertGreaterThanOrEqual(1, count($discountTypes));

        $discountType = null;
        foreach ($discountTypes as $type) {
            if ($type['type'] === 'cart_level') {
                $discountType = $type;
                break;
            }
        }
        $this->assertNotNull($discountType, 'Expected at least a discount type with cart_level type');

        $expectedCartLevelType = [
            'discountTypeId' => 2,
            'type' => 'cart_level',
            'names' => [
                'en-US' => 'On cart amount',
            ],
            'descriptions' => [
                'en-US' => 'Discount applied to cart',
            ],
            'core' => true,
            'enabled' => true,
        ];

        $this->assertEquals($expectedCartLevelType, $discountType);
    }
}
