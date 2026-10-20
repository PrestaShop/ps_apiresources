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

use Tests\Resources\DatabaseDump;

class CustomerThreadListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['customer_service_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables(['customer_thread', 'customer_message']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list customer threads endpoint' => ['GET', '/customer-threads'];
    }

    public function testListCustomerThreads(): void
    {
        $this->assertSame(0, $this->countItems('/customer-threads', ['customer_service_read']));

        $customerThreadId = $this->createCustomerThread(1, 'pub@prestashop.com', 'open', 'Where is my order?');
        $visitorThreadId = $this->createCustomerThread(0, 'visitor@example.com', 'closed', 'Do you ship abroad?');

        $paginatedThreads = $this->listItems('/customer-threads', ['customer_service_read']);
        $this->assertSame(2, $paginatedThreads['totalItems']);

        $thread = $paginatedThreads['items'][0];
        $this->assertIsString($thread['dateAdd']);
        $this->assertIsString($thread['dateUpd']);
        unset($thread['dateAdd'], $thread['dateUpd']);
        $this->assertSame([
            'customerThreadId' => $customerThreadId,
            'customerId' => 1,
            'customer' => 'John DOE',
            'email' => 'pub@prestashop.com',
            'orderId' => 0,
            'productId' => 0,
            'contactId' => 1,
            'contactName' => 'Webmaster',
            'shopId' => 1,
            'shopName' => 'test_shop',
            'languageId' => 1,
            'languageName' => 'English (English)',
            'status' => 'open',
            'lastMessage' => 'Where is my order?',
            'lastEmployee' => null,
        ], $thread);
        $this->assertNull($paginatedThreads['items'][1]['customer']);

        $listedIds = function (array $filters): array {
            return array_column($this->listItems('/customer-threads', ['customer_service_read'], $filters)['items'], 'customerThreadId');
        };
        $this->assertSame([$visitorThreadId], $listedIds(['status' => 'closed']));
        $this->assertSame([$visitorThreadId], $listedIds(['email' => 'visitor']));
        $this->assertSame([$customerThreadId], $listedIds(['customer' => 'DOE']));
        $this->assertSame([$customerThreadId], $listedIds(['lastMessage' => 'my order']));
        $this->assertSame([$customerThreadId, $visitorThreadId], $listedIds(['contactId' => 1, 'shopId' => 1]));
    }

    private function createCustomerThread(int $customerId, string $email, string $status, string $message): int
    {
        $customerThread = new \CustomerThread();
        $customerThread->id_shop = 1;
        $customerThread->id_lang = 1;
        $customerThread->id_contact = 1;
        $customerThread->id_customer = $customerId;
        $customerThread->id_order = 0;
        $customerThread->id_product = 0;
        $customerThread->status = $status;
        $customerThread->email = $email;
        $customerThread->token = \Tools::passwdGen(12);
        $customerThread->add();

        $customerMessage = new \CustomerMessage();
        $customerMessage->id_customer_thread = (int) $customerThread->id;
        $customerMessage->id_employee = 0;
        $customerMessage->message = $message;
        $customerMessage->private = false;
        $customerMessage->add();

        return (int) $customerThread->id;
    }
}
