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
    private static int $originalMailMethod;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['customer_service_read', 'customer_service_write']);

        // Replying and forwarding both send an email: disable the transport so they succeed
        // without an SMTP server.
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
            'customer_thread',
            'customer_message',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get details endpoint' => ['GET', '/customer-threads/1/details'];
        yield 'set-status endpoint' => ['PUT', '/customer-threads/1/set-status'];
        yield 'reply endpoint' => ['PUT', '/customer-threads/1/messages'];
        yield 'forward endpoint' => ['POST', '/customer-threads/1/forwards'];
        yield 'delete endpoint' => ['DELETE', '/customer-threads/1'];
        yield 'bulk delete endpoint' => ['DELETE', '/customer-threads/bulk-delete'];
    }

    /**
     * Customer threads are created by the front-office contact form: the CustomerService
     * domain exposes no "add thread" command, so there is no Admin API way to create one and
     * this is the single place where a fixture cannot come from the API. Everything the tests
     * below assert goes back through the API.
     */
    private function seedCustomerThread(string $status = 'open'): int
    {
        $thread = new \CustomerThread();
        $thread->id_lang = 1;
        $thread->id_contact = 1;
        $thread->id_shop = 1;
        $thread->id_customer = 0;
        $thread->id_order = 0;
        $thread->email = 'thread-test@example.com';
        // The column is 12 chars wide
        $thread->token = bin2hex(random_bytes(6));
        $thread->status = $status;
        $thread->add();

        return (int) $thread->id;
    }

    private function getDetails(int $customerThreadId): array
    {
        return $this->getItem('/customer-threads/' . $customerThreadId . '/details', ['customer_service_read']);
    }

    public function testGetCustomerThreadDetails(): void
    {
        $customerThreadId = $this->seedCustomerThread();

        $thread = $this->getDetails($customerThreadId);

        $this->assertEquals(
            [
                'customerThreadId',
                'languageId',
                'actions',
                'customerInformation',
                'contactName',
                'messages',
                'timeline',
            ],
            array_keys($thread)
        );
        $this->assertSame($customerThreadId, $thread['customerThreadId']);
        $this->assertSame(1, $thread['languageId']);
        $this->assertSame([], $thread['messages']);
    }

    public function testGetNonExistentCustomerThreadDetails(): void
    {
        $this->requestApi('GET', '/customer-threads/999999/details', null, ['customer_service_read'], Response::HTTP_NOT_FOUND);
    }

    public function testSetCustomerThreadStatus(): void
    {
        $customerThreadId = $this->seedCustomerThread('open');

        // An open thread offers the "close" action, a closed one offers "re-open" instead, so
        // the available actions are the read side of the status.
        $this->assertSame(['closed', 'pending1', 'pending2'], array_keys($this->getDetails($customerThreadId)['actions']));

        $this->updateItem(
            '/customer-threads/' . $customerThreadId . '/set-status',
            ['status' => 'closed'],
            ['customer_service_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertSame(['open', 'pending1', 'pending2'], array_keys($this->getDetails($customerThreadId)['actions']));
    }

    public function testSetInvalidStatusIsRejected(): void
    {
        $customerThreadId = $this->seedCustomerThread('open');

        $this->updateItem(
            '/customer-threads/' . $customerThreadId . '/set-status',
            ['status' => 'not-a-status'],
            ['customer_service_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        // The rejected update must not have changed the status
        $this->assertSame(['closed', 'pending1', 'pending2'], array_keys($this->getDetails($customerThreadId)['actions']));
    }

    public function testReplyToCustomerThread(): int
    {
        $customerThreadId = $this->seedCustomerThread();

        $this->updateItem(
            '/customer-threads/' . $customerThreadId . '/messages',
            ['replyMessage' => 'Thanks for reaching out, here is our reply.'],
            ['customer_service_write'],
            Response::HTTP_NO_CONTENT
        );

        // The reply is observed through the details endpoint instead of counting rows in
        // ps_customer_message.
        $messages = $this->getDetails($customerThreadId)['messages'];
        $this->assertCount(1, $messages);
        $this->assertSame('Thanks for reaching out, here is our reply.', $messages[0]['message']);

        return $customerThreadId;
    }

    /**
     * Forwarding builds its email from the last message of the thread, so it needs a thread
     * that has already been replied to — which the reply endpoint above provides.
     *
     * Requires PrestaShop/PrestaShop#42047: ForwardCustomerThreadCommand::__construct() is
     * private on the current cores, so the serializer cannot build the command. This test
     * stays red until that core PR is merged into the target branches.
     *
     * @depends testReplyToCustomerThread
     */
    public function testForwardCustomerThread(int $customerThreadId): void
    {
        $this->createItem(
            '/customer-threads/' . $customerThreadId . '/forwards',
            [
                'comment' => 'Forwarding this one for a second opinion.',
                'email' => 'someone.else@example.com',
            ],
            ['customer_service_write'],
            Response::HTTP_NO_CONTENT
        );

        // Forwarding appends a message to the thread
        $this->assertCount(2, $this->getDetails($customerThreadId)['messages']);
    }

    public function testDeleteCustomerThread(): void
    {
        $customerThreadId = $this->seedCustomerThread();

        $return = $this->deleteItem('/customer-threads/' . $customerThreadId, ['customer_service_write']);
        $this->assertNull($return);

        // The thread is really gone: the details endpoint no longer resolves it
        $this->requestApi(
            'GET',
            '/customer-threads/' . $customerThreadId . '/details',
            null,
            ['customer_service_read'],
            Response::HTTP_NOT_FOUND
        );
    }

    public function testBulkDeleteCustomerThreads(): void
    {
        $bulkIds = [$this->seedCustomerThread(), $this->seedCustomerThread()];

        $this->bulkDeleteItems('/customer-threads/bulk-delete', [
            'customerThreadIds' => $bulkIds,
        ], ['customer_service_write']);

        foreach ($bulkIds as $customerThreadId) {
            $this->requestApi(
                'GET',
                '/customer-threads/' . $customerThreadId . '/details',
                null,
                ['customer_service_read'],
                Response::HTTP_NOT_FOUND
            );
        }
    }
}
