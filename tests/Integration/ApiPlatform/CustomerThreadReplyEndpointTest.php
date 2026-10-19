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

class CustomerThreadReplyEndpointTest extends ApiTestCase
{
    private static int $originalMailMethod;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['customer_service_write']);

        // Disable real email sending so the reply email succeeds without an SMTP server.
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
        yield 'reply to customer thread endpoint' => ['PUT', '/customer-threads/1/messages'];
    }

    public function testReplyToCustomerThread(): void
    {
        $customerThreadId = $this->createCustomerThread();

        $this->updateItem(
            '/customer-threads/' . $customerThreadId . '/messages',
            ['replyMessage' => 'Thanks for reaching out, here is our reply.'],
            ['customer_service_write'],
            Response::HTTP_NO_CONTENT
        );

        $messageCount = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customer_message`
             WHERE `id_customer_thread` = ' . $customerThreadId
        );
        $this->assertSame(1, $messageCount);
    }

    private function createCustomerThread(): int
    {
        $customerId = (int) \Db::getInstance()->getValue(
            'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer` WHERE `active` = 1 ORDER BY `id_customer` ASC'
        );

        $customerThread = new \CustomerThread();
        $customerThread->id_lang = 1;
        $customerThread->id_contact = 1;
        $customerThread->id_shop = 1;
        $customerThread->id_customer = $customerId;
        $customerThread->email = 'thread-reply@example.com';
        $customerThread->status = 'open';
        $customerThread->token = bin2hex(random_bytes(6));
        $customerThread->add();

        return (int) $customerThread->id;
    }
}
