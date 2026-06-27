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

class CatalogPriceRuleEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['catalog_price_rule_read', 'catalog_price_rule_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['specific_price_rule']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/catalog-price-rules'];
        yield 'get endpoint' => ['GET', '/catalog-price-rules/1'];
        yield 'update endpoint' => ['PATCH', '/catalog-price-rules/1'];
        yield 'delete endpoint' => ['DELETE', '/catalog-price-rules/1'];
    }

    public function testAddCatalogPriceRule(): int
    {
        $catalogPriceRule = $this->createItem('/catalog-price-rules', $this->getCreatePayload(), ['catalog_price_rule_write']);

        $this->assertArrayHasKey('catalogPriceRuleId', $catalogPriceRule);

        return $catalogPriceRule['catalogPriceRuleId'];
    }

    /**
     * @depends testAddCatalogPriceRule
     */
    public function testGetCatalogPriceRule(int $catalogPriceRuleId): int
    {
        $catalogPriceRule = $this->getItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_read']);

        $this->assertEquals($catalogPriceRuleId, $catalogPriceRule['catalogPriceRuleId']);
        $this->assertEquals('Test catalog price rule', $catalogPriceRule['name']);
        $this->assertEquals(1, $catalogPriceRule['shopId']);
        $this->assertEquals(0, $catalogPriceRule['currencyId']);
        $this->assertEquals(0, $catalogPriceRule['countryId']);
        $this->assertEquals(0, $catalogPriceRule['groupId']);
        $this->assertEquals(1, $catalogPriceRule['fromQuantity']);
        $this->assertEquals('amount', $catalogPriceRule['reductionType']);
        $this->assertEquals(10.0, (float) $catalogPriceRule['reductionValue']);
        $this->assertTrue($catalogPriceRule['includeTax']);
        $this->assertEquals(-1.0, (float) $catalogPriceRule['price']);

        return $catalogPriceRuleId;
    }

    /**
     * @depends testGetCatalogPriceRule
     */
    public function testUpdateCatalogPriceRule(int $catalogPriceRuleId): int
    {
        $updatedRule = $this->partialUpdateItem(
            '/catalog-price-rules/' . $catalogPriceRuleId,
            [
                'name' => 'Updated catalog price rule',
                'fromQuantity' => 5,
                'reductionType' => 'percentage',
                'reductionValue' => '15',
            ],
            ['catalog_price_rule_write']
        );

        $this->assertEquals('Updated catalog price rule', $updatedRule['name']);
        $this->assertEquals(5, $updatedRule['fromQuantity']);
        $this->assertEquals('percentage', $updatedRule['reductionType']);
        $this->assertEquals(15.0, (float) $updatedRule['reductionValue']);

        // GET reflects the update
        $fetched = $this->getItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_read']);
        $this->assertEquals('Updated catalog price rule', $fetched['name']);
        $this->assertEquals('percentage', $fetched['reductionType']);

        return $catalogPriceRuleId;
    }

    /**
     * @depends testUpdateCatalogPriceRule
     */
    public function testDeleteCatalogPriceRule(int $catalogPriceRuleId): void
    {
        $this->deleteItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_write']);
        $this->getItem(
            '/catalog-price-rules/' . $catalogPriceRuleId,
            ['catalog_price_rule_read'],
            Response::HTTP_NOT_FOUND
        );
    }

    public function testInvalidCatalogPriceRule(): void
    {
        $invalidData = array_merge($this->getCreatePayload(), [
            // Name is required (NotBlank) on creation.
            'name' => '',
        ]);

        $validationErrorsResponse = $this->createItem(
            '/catalog-price-rules',
            $invalidData,
            ['catalog_price_rule_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'name',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);
    }

    private function getCreatePayload(): array
    {
        return [
            'name' => 'Test catalog price rule',
            'shopId' => 1,
            'currencyId' => 0,
            'countryId' => 0,
            'groupId' => 0,
            'fromQuantity' => 1,
            'reductionType' => 'amount',
            'reductionValue' => '10',
            'includeTax' => true,
            'price' => -1.0,
        ];
    }
}
