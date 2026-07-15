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

class OrderRefundEndpointsTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'issue standard refund endpoint' => ['POST', '/orders/1/refunds'];
        yield 'issue return product endpoint' => ['POST', '/orders/1/product-returns'];
    }

    public function testIssueStandardRefundRejectsMissingOrderDetailRefunds(): void
    {
        $this->createItem(
            '/orders/1/refunds',
            [
                'orderDetailRefunds' => [],
                'refundShippingCost' => false,
                'generateCreditSlip' => false,
                'generateVoucher' => false,
                'voucherRefundType' => 1,
            ],
            ['order_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function testIssueProductReturnRejectsMissingOrderDetailRefunds(): void
    {
        $this->createItem(
            '/orders/1/product-returns',
            [
                'orderDetailRefunds' => [],
                'restockRefundedProducts' => false,
                'refundShippingCost' => false,
                'generateCreditSlip' => false,
                'generateVoucher' => false,
                'voucherRefundType' => 1,
            ],
            ['order_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
