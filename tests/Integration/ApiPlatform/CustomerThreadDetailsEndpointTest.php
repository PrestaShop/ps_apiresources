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

class CustomerThreadDetailsEndpointTest extends ApiTestCase
{
    private static int $customerThreadId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['customer_service_read']);

        // No "Add customer thread" command exists, so seed one through the legacy object.
        // id_customer = 0 keeps the customer-information path on the lightweight
        // "email only" branch, and id_order = 0 skips the order-history timeline.
        $customerThread = new \CustomerThread();
        $customerThread->id_lang = 1;
        $customerThread->id_contact = 1;
        $customerThread->id_shop = 1;
        $customerThread->id_customer = 0;
        $customerThread->id_order = 0;
        $customerThread->email = 'thread-details@example.com';
        $customerThread->status = 'open';
        $customerThread->token = bin2hex(random_bytes(6));
        $customerThread->add();

        self::$customerThreadId = (int) $customerThread->id;
    }

    public static function tearDownAfterClass(): void
    {
        $customerThread = new \CustomerThread(self::$customerThreadId);
        if (\Validate::isLoadedObject($customerThread)) {
            $customerThread->delete();
        }

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get customer thread details endpoint' => ['GET', '/customer-threads/1/details'];
    }

    public function testGetCustomerThreadDetails(): void
    {
        $thread = $this->getItem('/customer-threads/' . self::$customerThreadId . '/details', ['customer_service_read']);

        $this->assertArrayHasKey('customerThreadId', $thread);
        $this->assertSame(self::$customerThreadId, $thread['customerThreadId']);
        $this->assertArrayHasKey('languageId', $thread);
        $this->assertSame(1, $thread['languageId']);
        $this->assertArrayHasKey('actions', $thread);
        $this->assertArrayHasKey('customerInformation', $thread);
        $this->assertArrayHasKey('contactName', $thread);
        $this->assertArrayHasKey('messages', $thread);
        $this->assertArrayHasKey('timeline', $thread);
    }

    public function testGetNonExistentCustomerThreadDetails(): void
    {
        $this->requestApi('GET', '/customer-threads/999999/details', null, ['customer_service_read'], Response::HTTP_NOT_FOUND);
    }
}
