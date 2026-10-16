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

class SpecificPriceEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['specific_price']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['specific_price']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/specific-prices'];
        yield 'get endpoint' => ['GET', '/specific-prices/1'];
        yield 'update endpoint' => ['PATCH', '/specific-prices/1'];
        yield 'delete endpoint' => ['DELETE', '/specific-prices/1'];
    }

    public function testAddSpecificPrice(): int
    {
        $specificPrice = $this->requestApi('POST', '/specific-prices', [
            'productId' => 1,
            'reductionType' => 'amount',
            'reductionValue' => '5.000000',
            'includeTax' => true,
            'fixedPrice' => '-1',
            'fromQuantity' => 1,
            'dateTimeFrom' => '2026-06-01 00:00:00',
            'dateTimeTo' => '2026-06-30 00:00:00',
        ], ['specific_price_write'], Response::HTTP_CREATED);

        $this->assertArrayHasKey('specificPriceId', $specificPrice);
        $this->assertIsInt($specificPrice['specificPriceId']);
        $this->assertGreaterThan(0, $specificPrice['specificPriceId']);
        $this->assertSame(1, $specificPrice['productId']);
        $this->assertSame('amount', $specificPrice['reductionType']);

        return $specificPrice['specificPriceId'];
    }

    /**
     * @depends testAddSpecificPrice
     */
    public function testGetSpecificPrice(int $specificPriceId): int
    {
        $specificPrice = $this->getItem('/specific-prices/' . $specificPriceId, ['specific_price_read']);

        $this->assertSame($specificPriceId, $specificPrice['specificPriceId']);
        $this->assertEquals(5, $specificPrice['reductionValue']);
        $this->assertSame(1, $specificPrice['fromQuantity']);

        return $specificPriceId;
    }

    /**
     * @depends testGetSpecificPrice
     */
    public function testUpdateSpecificPrice(int $specificPriceId): int
    {
        $specificPrice = $this->requestApi('PATCH', '/specific-prices/' . $specificPriceId, [
            'reductionType' => 'amount',
            'reductionValue' => '8.000000',
            'fromQuantity' => 3,
        ], ['specific_price_write'], Response::HTTP_OK);

        $this->assertEquals(8, $specificPrice['reductionValue']);
        $this->assertSame(3, $specificPrice['fromQuantity']);

        return $specificPriceId;
    }

    /**
     * @depends testUpdateSpecificPrice
     */
    public function testDeleteSpecificPrice(int $specificPriceId): void
    {
        $this->requestApi('DELETE', '/specific-prices/' . $specificPriceId, null, ['specific_price_write'], Response::HTTP_NO_CONTENT);
        $this->getItem('/specific-prices/' . $specificPriceId, ['specific_price_read'], Response::HTTP_NOT_FOUND);
    }
}
