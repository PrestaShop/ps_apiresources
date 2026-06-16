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

class CustomerThreadEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['customer_service_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'customer_thread',
            'customer_message',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set-status endpoint' => ['PUT', '/customer-threads/1/set-status'];
        yield 'delete endpoint' => ['DELETE', '/customer-threads/1'];
        yield 'bulk delete endpoint' => ['DELETE', '/customer-threads/bulk-delete'];
    }

    private function createCustomerThread(string $status = 'open'): int
    {
        $thread = new \CustomerThread();
        $thread->id_lang = 1;
        $thread->id_contact = 1;
        $thread->id_shop = 1;
        $thread->id_customer = 0;
        $thread->email = 'thread-test@example.com';
        $thread->token = 'token-' . uniqid();
        $thread->status = $status;
        $thread->add();

        return (int) $thread->id;
    }

    public function testSetCustomerThreadStatus(): void
    {
        $customerThreadId = $this->createCustomerThread('open');

        $this->updateItem(
            '/customer-threads/' . $customerThreadId . '/set-status',
            ['status' => 'closed'],
            ['customer_service_write'],
            Response::HTTP_NO_CONTENT
        );

        $thread = new \CustomerThread($customerThreadId);
        $this->assertSame('closed', $thread->status);
    }

    public function testSetInvalidStatusIsRejected(): void
    {
        $customerThreadId = $this->createCustomerThread('open');

        $this->updateItem(
            '/customer-threads/' . $customerThreadId . '/set-status',
            ['status' => 'not-a-status'],
            ['customer_service_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function testDeleteCustomerThread(): void
    {
        $customerThreadId = $this->createCustomerThread('open');

        $return = $this->deleteItem('/customer-threads/' . $customerThreadId, ['customer_service_write']);
        $this->assertNull($return);

        $this->assertFalse(\Validate::isLoadedObject(new \CustomerThread($customerThreadId)));
    }

    public function testBulkDeleteCustomerThreads(): void
    {
        $firstId = $this->createCustomerThread('open');
        $secondId = $this->createCustomerThread('open');

        $this->bulkDeleteItems('/customer-threads/bulk-delete', [
            'customerThreadIds' => [$firstId, $secondId],
        ], ['customer_service_write']);

        $this->assertFalse(\Validate::isLoadedObject(new \CustomerThread($firstId)));
        $this->assertFalse(\Validate::isLoadedObject(new \CustomerThread($secondId)));
    }
}
