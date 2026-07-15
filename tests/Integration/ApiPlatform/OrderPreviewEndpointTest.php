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

class OrderPreviewEndpointTest extends ApiTestCase
{
    public static function getProtectedEndpoints(): iterable
    {
        yield 'get order preview endpoint' => ['GET', '/orders/1/preview'];
    }

    public function testGetOrderPreview(): void
    {
        $orderId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` ASC'
        );

        $preview = $this->getItem('/orders/' . $orderId . '/preview', ['order_read']);

        $this->assertSame($orderId, $preview['orderId']);
        $this->assertIsBool($preview['taxIncluded']);
        $this->assertIsBool($preview['virtual']);
        $this->assertNotEmpty($preview['invoiceAddressFormatted']);
        $this->assertNotEmpty($preview['shippingAddressFormatted']);
        $this->assertIsArray($preview['productDetails']);
        $this->assertNotEmpty($preview['productDetails']);

        $firstProduct = $preview['productDetails'][0];
        $this->assertArrayHasKey('id', $firstProduct);
        $this->assertArrayHasKey('name', $firstProduct);
        $this->assertArrayHasKey('quantity', $firstProduct);
        $this->assertArrayHasKey('unitPrice', $firstProduct);
        $this->assertArrayHasKey('totalPrice', $firstProduct);
    }

    public function testGetNonExistentOrderPreviewReturnsNotFound(): void
    {
        $this->getItem('/orders/999999/preview', ['order_read'], Response::HTTP_NOT_FOUND);
    }
}
