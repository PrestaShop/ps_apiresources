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

class BackOfficeOrderEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'add order from back office endpoint' => ['POST', '/orders'];
    }

    public function testAddOrderWithMalformedPaymentModuleNameReturns422(): void
    {
        // A malformed paymentModuleName ("bad module!") passes Assert\NotBlank
        // but AddOrderFromBackOfficeCommand::assertIsModuleName() throws
        // InvalidModuleException — must surface as 422, not 500.
        $this->createItem(
            '/orders',
            [
                'cartId' => 1,
                'paymentModuleName' => 'bad module!',
                'orderStateId' => 1,
            ],
            ['order_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
