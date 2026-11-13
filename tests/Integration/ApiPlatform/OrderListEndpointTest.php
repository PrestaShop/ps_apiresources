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

class OrderListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list orders endpoint' => ['GET', '/orders'];
    }

    public function testListOrders(): void
    {
        // The test shop is installed with five fixture orders, all placed by John DOE
        $paginatedOrders = $this->listItems('/orders?orderBy=orderId&sortOrder=asc&limit=2', ['order_read']);
        $this->assertSame(5, $paginatedOrders['totalItems']);
        $this->assertSame('orderId', $paginatedOrders['orderBy']);
        $this->assertCount(2, $paginatedOrders['items']);

        $order = $paginatedOrders['items'][0];
        $this->assertIsString($order['dateAdd']);
        unset($order['dateAdd']);
        $this->assertEquals([
            'orderId' => 1,
            'reference' => 'XKBKNABJK',
            'customerId' => 1,
            'customer' => 'J. DOE',
            'customerDeleted' => false,
            'newCustomer' => true,
            'companyName' => '',
            'totalPaidTaxIncl' => 68.2,
            'currencyId' => 1,
            'currencyIsoCode' => 'USD',
            'orderStateId' => 6,
            'orderStateName' => 'Canceled',
            'orderStateColor' => '#2C3E50',
            'paid' => false,
            'payment' => 'Payment by check',
            'shopName' => 'test_shop',
            'countryName' => 'United States',
            'invoiceNumber' => 0,
            'deliveryNumber' => 0,
        ], $order);
        $this->assertSame(2, $paginatedOrders['items'][1]['orderId']);
    }

    public function testFilterOrders(): void
    {
        $listedIds = function (array $filters): array {
            $paginatedOrders = $this->listItems('/orders?orderBy=orderId&sortOrder=asc', ['order_read'], $filters);

            return array_column($paginatedOrders['items'], 'orderId');
        };

        $this->assertSame([1], $listedIds(['orderId' => 1]));
        $this->assertSame([1], $listedIds(['reference' => 'XKBK']));
        $this->assertSame([5], $listedIds(['payment' => 'wire']));
        $this->assertSame([2, 4], $listedIds(['orderStateId' => 1]));
        $this->assertSame([3, 4], $listedIds(['totalPaidTaxIncl' => '21.3']));
        $this->assertSame([1, 2, 3, 4, 5], $listedIds(['customer' => 'DOE']));
        $this->assertSame([1, 2, 3, 4, 5], $listedIds(['countryId' => (int) \Country::getByIso('US')]));
        $this->assertSame([], $listedIds(['countryId' => (int) \Country::getByIso('FR')]));
    }
}
