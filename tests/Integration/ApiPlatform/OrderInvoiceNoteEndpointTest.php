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

class OrderInvoiceNoteEndpointTest extends ApiTestCase
{
    private static int $orderInvoiceId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        self::$orderInvoiceId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order_invoice` FROM `' . _DB_PREFIX_ . 'order_invoice` ORDER BY `id_order_invoice` ASC'
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['order_invoice']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update invoice note endpoint' => ['PUT', '/orders/invoices/1/notes'];
    }

    public function testUpdateOrderInvoiceNote(): void
    {
        $note = 'Invoice note set through the Admin API';

        $this->updateItem(
            '/orders/invoices/' . self::$orderInvoiceId . '/notes',
            ['note' => $note],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );

        $storedNote = \Db::getInstance()->getValue(
            'SELECT `note` FROM `' . _DB_PREFIX_ . 'order_invoice` WHERE `id_order_invoice` = ' . self::$orderInvoiceId
        );
        $this->assertSame($note, $storedNote);
    }
}
