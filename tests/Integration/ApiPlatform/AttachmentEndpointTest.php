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

class AttachmentEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['attachment', 'attachment_lang']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['attachment', 'attachment_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/attachments',
            'multipart/form-data',
        ];

        yield 'get endpoint' => [
            'GET',
            '/attachments/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/attachments/1',
            'multipart/form-data',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/attachments/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/attachments',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/attachments/bulk-delete',
        ];
    }

    public function testAddAttachment(): int
    {
        $uploadedFile = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/Hummingbird_cushion.jpg');

        $attachment = $this->requestApi('POST', '/attachments', null, ['attachment_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => $uploadedFile,
                ],
                'parameters' => [
                    'names' => [
                        'en-US' => 'Documentation',
                    ],
                    'descriptions' => [
                        'en-US' => 'A product documentation file',
                    ],
                ],
            ],
        ]);

        $this->assertArrayHasKey('attachmentId', $attachment);
        $this->assertIsInt($attachment['attachmentId']);
        $this->assertGreaterThan(0, $attachment['attachmentId']);

        return $attachment['attachmentId'];
    }

    /**
     * @depends testAddAttachment
     */
    public function testGetAttachment(int $attachmentId): int
    {
        $attachment = $this->getItem('/attachments/' . $attachmentId, ['attachment_read']);

        $this->assertArrayHasKey('names', $attachment);
        $this->assertArrayHasKey('fileName', $attachment);
        $this->assertSame('Documentation', reset($attachment['names']));

        return $attachmentId;
    }

    /**
     * @depends testGetAttachment
     */
    public function testUpdateAttachment(int $attachmentId): int
    {
        $attachment = $this->requestApi('PATCH', '/attachments/' . $attachmentId, null, ['attachment_write'], Response::HTTP_OK, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'names' => [
                        'en-US' => 'Updated documentation',
                    ],
                    'descriptions' => [
                        'en-US' => 'An updated description',
                    ],
                ],
            ],
        ]);

        $this->assertSame('Updated documentation', reset($attachment['names']));

        return $attachmentId;
    }

    /**
     * @depends testUpdateAttachment
     */
    public function testListAttachments(int $attachmentId): int
    {
        $attachments = $this->listItems('/attachments?orderBy=attachmentId&sortOrder=desc', ['attachment_read']);
        $this->assertGreaterThanOrEqual(1, $attachments['totalItems']);
        $this->assertEquals('attachmentId', $attachments['orderBy']);

        // The attachment created above is the most recent, so it comes first in the descending list.
        $listed = $attachments['items'][0];
        $this->assertEquals($attachmentId, $listed['attachmentId']);
        $this->assertSame('Updated documentation', $listed['name']);
        // The grid decorator exposes file_size and products as formatted strings.
        $this->assertArrayHasKey('file', $listed);
        $this->assertArrayHasKey('fileSize', $listed);
        $this->assertArrayHasKey('products', $listed);

        // The filter parameter is mapped to the grid's id_attachment column.
        $filtered = $this->listItems('/attachments', ['attachment_read'], [
            'attachmentId' => $attachmentId,
        ]);
        $this->assertEquals(1, $filtered['totalItems']);
        $this->assertEquals($attachmentId, $filtered['items'][0]['attachmentId']);

        return $attachmentId;
    }

    /**
     * @depends testListAttachments
     */
    public function testDeleteAttachment(int $attachmentId): void
    {
        $this->requestApi('DELETE', '/attachments/' . $attachmentId, null, ['attachment_write'], Response::HTTP_NO_CONTENT);
        $this->getItem('/attachments/' . $attachmentId, ['attachment_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteAttachments(): void
    {
        $attachmentIds = [
            $this->createAttachment('Bulk attachment one'),
            $this->createAttachment('Bulk attachment two'),
            $this->createAttachment('Bulk attachment three'),
        ];

        $this->bulkDeleteItems('/attachments/bulk-delete', ['attachmentIds' => $attachmentIds], ['attachment_write']);

        foreach ($attachmentIds as $attachmentId) {
            $this->getItem('/attachments/' . $attachmentId, ['attachment_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testBulkDeleteAttachmentsValidationErrors(): void
    {
        // An empty id list must be rejected with a 422 validation error.
        $this->bulkDeleteItems('/attachments/bulk-delete', ['attachmentIds' => []], ['attachment_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function createAttachment(string $name): int
    {
        $uploadedFile = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/Hummingbird_cushion.jpg');

        $attachment = $this->requestApi('POST', '/attachments', null, ['attachment_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => $uploadedFile,
                ],
                'parameters' => [
                    'names' => [
                        'en-US' => $name,
                    ],
                    'descriptions' => [
                        'en-US' => 'A product documentation file',
                    ],
                ],
            ],
        ]);

        return $attachment['attachmentId'];
    }
}
