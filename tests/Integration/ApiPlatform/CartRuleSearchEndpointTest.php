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
use Tests\Resources\DatabaseDump;

class CartRuleSearchEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['cart_rule_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'cart_rule',
            'cart_rule_lang',
            'cart_rule_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'search cart rules endpoint' => [
            'GET',
            '/cart-rules/search?phrase=test',
        ];
    }

    public function testSearchCartRules(): void
    {
        $searchResults = $this->getItem('/cart-rules/search?phrase=test', ['cart_rule_read']);

        $this->assertIsArray($searchResults);

        foreach ($searchResults as $result) {
            $this->assertArrayHasKey('cartRuleId', $result);
            $this->assertArrayHasKey('name', $result);
            $this->assertArrayHasKey('code', $result);
        }
    }

    public function testSearchCartRulesWithNoResults(): void
    {
        $searchResults = $this->getItem('/cart-rules/search?phrase=nonexistentcartrule999', ['cart_rule_read']);

        $this->assertIsArray($searchResults);
        $this->assertEmpty($searchResults);
    }

    public function testSearchCartRulesMissingPhrase(): void
    {
        $this->requestApi('GET', '/cart-rules/search', null, ['cart_rule_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
