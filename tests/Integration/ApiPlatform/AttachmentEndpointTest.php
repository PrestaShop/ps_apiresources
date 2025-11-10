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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class AttachmentEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        self::createApiClient(['attachment_read', 'attachment_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'attachment',
            'attachment_lang',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/attachments/1',
        ];

        yield 'create endpoint' => [
            'POST', '/attachments',
            'multipart/form-data',
        ];

        yield 'update endpoint' => [
            'POST', '/attachments/1',
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
            '/attachments/batch',
        ];
    }

    public function testAddAttachment(): int
    {
        $attachment = $this->createAttachment();

        $this->assertArrayHasKey('attachmentId', $attachment);
        $this->assertIsInt($attachment['attachmentId']);
        $this->assertSame('Attachment EN', $attachment['names']['en-US']);
        $this->assertSame('Attachment FR', $attachment['names']['fr-FR']);

        return $attachment['attachmentId'];
    }

    /**
     * @depends testAddAttachment
     */
    public function testGetAttachment(int $attachmentId): int
    {
        $attachment = $this->getItem('/attachments/' . $attachmentId, ['attachment_read']);
        $this->assertEquals($attachmentId, $attachment['attachmentId']);
        $this->assertArrayHasKey('names', $attachment);

        return $attachmentId;
    }

    /**
     * @depends testGetAttachment
     */
    public function testUpdateAttachment(int $attachmentId): int
    {
        $uploadedFile = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/Brown_bear_cushion.jpg');

        // Special type of request, requires multipart/form-data content-type and upload a file via the request
        $updated = $this->requestApi(Request::METHOD_POST, '/attachments/' . $attachmentId, null, ['attachment_write'], Response::HTTP_OK, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'names' => [
                        'en-US' => 'Updated attachment EN',
                        'fr-FR' => 'Updated attachment FR',
                    ],
                    'descriptions' => [
                        'en-US' => 'Updated description EN',
                        'fr-FR' => 'Updated description FR',
                    ],
                ],
                'files' => [
                    'attachment' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertArrayHasKey('attachmentId', $updated);
        $this->assertEquals($attachmentId, $updated['attachmentId']);
        $this->assertSame('Updated attachment EN', $updated['names']['en-US']);

        return $attachmentId;
    }

    /**
     * @depends testUpdateAttachment
     */
    public function testListAttachments(int $attachmentId): int
    {
        $list = $this->listItems('/attachments?orderBy=attachmentId&sortOrder=desc', ['attachment_read']);
        $this->assertGreaterThanOrEqual(1, $list['totalItems']);
        $this->assertEquals('attachmentId', $list['orderBy']);

        $first = $list['items'][0];
        $this->assertEquals($attachmentId, $first['attachmentId']);

        return $attachmentId;
    }

    /**
     * @depends testListAttachments
     */
    public function testRemoveAttachment(int $attachmentId): void
    {
        $this->deleteItem('/attachments/' . $attachmentId, ['attachment_write']);
        $this->getItem('/attachments/' . $attachmentId, ['attachment_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteAttachments(): void
    {
        $removeAttachmentIds = [];
        for ($count = 0; $count < 3; ++$count) {
            $attachment = $this->createAttachment();
            $removeAttachmentIds[] = $attachment['attachmentId'];
        }

        $this->deleteBatch('/attachments/batch', [
            'attachmentIds' => $removeAttachmentIds,
        ], ['attachment_write'], Response::HTTP_NO_CONTENT);

        foreach ($removeAttachmentIds as $attachmentId) {
            $this->getItem('/attachments/' . $attachmentId, ['attachment_read'], Response::HTTP_NOT_FOUND);
        }
    }

    protected function createAttachment(): array|string|null
    {
        $uploadedFile = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/Brown_bear_cushion.jpg');

        // Special type of request, requires multipart/form-data content-type and upload a file via the request
        return $this->requestApi(Request::METHOD_POST, '/attachments', null, ['attachment_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'names' => [
                        'en-US' => 'Attachment EN',
                        'fr-FR' => 'Attachment FR',
                    ],
                    'descriptions' => [
                        'en-US' => 'Test description EN',
                        'fr-FR' => 'Description FR',
                    ],
                ],
                'files' => [
                    'attachment' => $uploadedFile,
                ],
            ],
        ]);
    }

    protected function deleteBatch(string $endPointUrl, ?array $data, array $scopes = [], ?int $expectedHttpCode = null, ?array $requestOptions = null): array|string|null
    {
        return $this->requestApi(Request::METHOD_DELETE, $endPointUrl, $data, $scopes, $expectedHttpCode, $requestOptions);
    }
}
