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

namespace PsApiResourcesTest\Integration\ApiPlatform;

use Symfony\Component\HttpFoundation\Response;

class CartRuleEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Pre-create an API Client with needed scopes to reduce token creations
        self::createApiClient(['cart_rule_write', 'discount_write', 'discount_read']);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'update cart rule' => [
            'PATCH',
            '/cart-rule/1',
        ];
    }

    public function testPatchCartRule(): void
    {
        $discount = $this->createItem('/discount', [
            'type' => 'order_level',
            'names' => [
                'en-US' => 'Initial cart rule',
            ],
        ], ['discount_write']);
        $cartRuleId = $discount['discountId'];

        $from = '2023-01-01T00:00:00+00:00';
        $to = '2033-01-01T00:00:00+00:00';

        $updated = $this->partialUpdateItem('/cart-rule/' . $cartRuleId, [
            'description' => 'Updated description',
            'localizedNames' => [
                'en-US' => 'Updated cart rule',
            ],
            'validityDateRange' => [
                'from' => $from,
                'to' => $to,
            ],
            'active' => true,
        ], ['cart_rule_write']);

        $this->assertEquals('Updated description', $updated['description']);
        $this->assertEquals('Updated cart rule', $updated['localizedNames']['en-US']);
        $this->assertEquals($from, $updated['validityDateRange']['from']);
        $this->assertEquals($to, $updated['validityDateRange']['to']);
        $this->assertTrue($updated['active']);

        $reloaded = $this->getItem('/discount/' . $cartRuleId, ['discount_read']);
        $this->assertEquals('Updated description', $reloaded['description']);
        $this->assertEquals('Updated cart rule', $reloaded['names']['en-US']);
        $this->assertEquals($from, $reloaded['validFrom']);
        $this->assertEquals($to, $reloaded['validTo']);
    }

    public function testPatchCartRuleInvalidData(): void
    {
        $discount = $this->createItem('/discount', [
            'type' => 'order_level',
            'names' => [
                'en-US' => 'Initial cart rule',
            ],
        ], ['discount_write']);
        $cartRuleId = $discount['discountId'];

        // Invalid dates: from is later than to
        $validationErrors = $this->partialUpdateItem('/cart-rule/' . $cartRuleId, [
            'validityDateRange' => [
                'from' => '2030-01-01T00:00:00+00:00',
                'to' => '2020-01-01T00:00:00+00:00',
            ],
        ], ['cart_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            [
                'propertyPath' => '',
                'message' => '',
            ],
        ], $validationErrors);

        // Missing localized name
        $validationErrors = $this->partialUpdateItem('/cart-rule/' . $cartRuleId, [
            'localizedNames' => [],
        ], ['cart_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            [
                'propertyPath' => '',
                'message' => '',
            ],
        ], $validationErrors);
    }
}
