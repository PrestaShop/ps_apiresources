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

class CartProcessOrderEmailEndpointTest extends ApiTestCase
{
    private static int $originalMailMethod;

    public static function setUpBeforeClass(): void
    {
        // The cart fixture goes through POST /carts (PrestaShop/ps_apiresources#201), gated on 9.2.0
        if (self::isVersionUnder('9.2.0')) {
            static::markTestSkipped('POST /carts requires PrestaShop >= 9.2.0, see Cart::VERSION_GATE');

            return;
        }

        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['cart_write', 'customer_write', 'order_write']);

        // The process-order email is sent for real otherwise
        self::$originalMailMethod = (int) \Configuration::get('PS_MAIL_METHOD');
        \Configuration::updateValue('PS_MAIL_METHOD', \Mail::METHOD_DISABLE);
    }

    public static function tearDownAfterClass(): void
    {
        \Configuration::updateValue('PS_MAIL_METHOD', self::$originalMailMethod);

        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'cart',
            'customer',
            'customer_group',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'send process order email endpoint' => ['PUT', '/carts/1/process-order-emails'];
    }

    public function testSendProcessOrderEmail(): void
    {
        $customerId = $this->createItem('/customers', [
            'firstName' => 'Jane',
            'lastName' => 'PROCESS',
            'email' => 'jane.process-order@example.com',
            'password' => 'TestPassword123!',
            'genderId' => 2,
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'enabled' => true,
        ], ['customer_write'])['customerId'];
        $cartId = $this->createItem('/carts', ['customerId' => $customerId], ['cart_write'])['cartId'];

        // The command only sends the email, it has no read side to replay
        $this->assertNull($this->updateItem('/carts/' . $cartId . '/process-order-emails', null, ['order_write'], Response::HTTP_NO_CONTENT));
    }

    public function testSendProcessOrderEmailForUnknownCart(): void
    {
        $this->updateItem('/carts/999999/process-order-emails', null, ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testSendProcessOrderEmailWithInvalidCartId(): void
    {
        // cartId=0 passes the URI \d+ requirement but new CartId(0) throws CartConstraintException
        $this->updateItem('/carts/0/process-order-emails', null, ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
