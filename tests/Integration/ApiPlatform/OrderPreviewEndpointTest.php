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
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get order preview endpoint' => ['GET', '/orders/1/previews'];
    }

    public function testGetOrderPreview(): void
    {
        $orderId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` ASC'
        );

        $preview = $this->getItem('/orders/' . $orderId . '/preview', ['order_read']);

        $this->assertArrayHasKey('orderId', $preview);
        $this->assertSame($orderId, $preview['orderId']);
        $this->assertArrayHasKey('invoiceDetails', $preview);
        $this->assertArrayHasKey('shippingDetails', $preview);
        $this->assertArrayHasKey('productDetails', $preview);
        $this->assertArrayHasKey('taxIncluded', $preview);
        $this->assertArrayHasKey('virtual', $preview);
        $this->assertArrayHasKey('invoiceAddressFormatted', $preview);
        $this->assertArrayHasKey('shippingAddressFormatted', $preview);
    }

    public function testGetNonExistentOrderPreviewReturnsNotFound(): void
    {
        $this->requestApi('GET', '/orders/999999/preview', null, ['order_read'], Response::HTTP_NOT_FOUND);
    }
}
