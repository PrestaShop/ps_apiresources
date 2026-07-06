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

class OrderEmailEndpointTest extends ApiTestCase
{
    private static int $originalMailMethod;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        // Disable real email sending so OrderHistory::sendEmail() succeeds without an SMTP server.
        self::$originalMailMethod = (int) \Configuration::get('PS_MAIL_METHOD');
        \Configuration::updateValue('PS_MAIL_METHOD', \Mail::METHOD_DISABLE);
    }

    public static function tearDownAfterClass(): void
    {
        \Configuration::updateValue('PS_MAIL_METHOD', self::$originalMailMethod);

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'resend order email endpoint' => ['PUT', '/orders/1/emails'];
    }

    public function testResendOrderEmail(): void
    {
        $history = \Db::getInstance()->getRow(
            'SELECT `id_order`, `id_order_history`, `id_order_state`
             FROM `' . _DB_PREFIX_ . 'order_history` ORDER BY `id_order_history` ASC'
        );

        $this->updateItem(
            '/orders/' . (int) $history['id_order'] . '/emails',
            [
                'orderStatusId' => (int) $history['id_order_state'],
                'orderHistoryId' => (int) $history['id_order_history'],
            ],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );
    }
}
