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
    public function testDeleteAttachment(int $attachmentId): void
    {
        $this->requestApi('DELETE', '/attachments/' . $attachmentId, null, ['attachment_write'], Response::HTTP_NO_CONTENT);
        $this->getItem('/attachments/' . $attachmentId, ['attachment_read'], Response::HTTP_NOT_FOUND);
    }
}
