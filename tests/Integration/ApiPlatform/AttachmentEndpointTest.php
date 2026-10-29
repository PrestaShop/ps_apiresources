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
    private const ASSET = __DIR__ . '/../../Resources/assets/image/Hummingbird_cushion.jpg';

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
        // The single item operations are filtered out below PrestaShop 9.2.1, where they answer 404 instead of 401
        if (self::isVersionAtLeast('9.2.1')) {
            yield 'create endpoint' => ['POST', '/attachments', 'multipart/form-data'];
            yield 'get endpoint' => ['GET', '/attachments/1'];
            yield 'update endpoint' => ['POST', '/attachments/1', 'multipart/form-data'];
        }
        yield 'delete endpoint' => ['DELETE', '/attachments/1'];
        yield 'list endpoint' => ['GET', '/attachments'];
        yield 'bulk delete endpoint' => ['DELETE', '/attachments/bulk-delete'];
    }

    public function testAddAttachment(): int
    {
        $this->markTestSkippedByMinVersion('9.2.1');

        $attachment = $this->sendAttachment('/attachments', $this->getFormFields('Documentation', 'A product documentation file'), true, Response::HTTP_CREATED);
        $this->assertIsInt($attachment['attachmentId']);
        $this->assertEquals($this->expectedAttachment($attachment['attachmentId'], 'Documentation', 'A product documentation file'), $attachment);

        return $attachment['attachmentId'];
    }

    /**
     * @depends testAddAttachment
     */
    public function testGetAttachment(int $attachmentId): int
    {
        $this->assertEquals(
            $this->expectedAttachment($attachmentId, 'Documentation', 'A product documentation file'),
            $this->getItem('/attachments/' . $attachmentId, ['attachment_read'])
        );

        return $attachmentId;
    }

    /**
     * @depends testGetAttachment
     */
    public function testUpdateAttachment(int $attachmentId): int
    {
        // Without a file the current one is kept
        $expected = $this->expectedAttachment($attachmentId, 'Updated documentation', 'An updated description');
        $this->assertEquals($expected, $this->sendAttachment('/attachments/' . $attachmentId, $this->getFormFields('Updated documentation', 'An updated description'), false));
        $this->assertEquals($expected, $this->getItem('/attachments/' . $attachmentId, ['attachment_read']));
        // Not covered with a file: AttachmentFileUploader stores it with move_uploaded_file(), which stores nothing for a
        // test upload, so replacing it would only unlink a missing file

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
        $this->assertSame($attachmentId, $listed['attachmentId']);
        $this->assertSame('Updated documentation', $listed['name']);
        $this->assertSame(filesize(self::ASSET), $listed['fileSize']);
        $this->assertSame(0, $listed['products']);
        $this->assertIsString($listed['file']);

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
    public function testUpdateAttachmentWithInvalidData(int $attachmentId): int
    {
        // EditAttachmentHandler writes the names and the descriptions whatever was sent, so both are required
        $response = $this->sendAttachment('/attachments/' . $attachmentId, ['names' => ['en-US' => 'Updated documentation']], false, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'descriptions', 'message' => 'This value should not be null.'],
        ], $response);
        $response = $this->sendAttachment('/attachments/' . $attachmentId, ['descriptions' => ['en-US' => 'An updated description']], false, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'names', 'message' => 'This value should not be blank.'],
        ], $response);
        $this->assertSame('Updated documentation', $this->getItem('/attachments/' . $attachmentId, ['attachment_read'])['names']['en-US']);

        return $attachmentId;
    }

    /**
     * @depends testUpdateAttachmentWithInvalidData
     */
    public function testDeleteAttachment(int $attachmentId): void
    {
        $this->requestApi('DELETE', '/attachments/' . $attachmentId, null, ['attachment_write'], Response::HTTP_NO_CONTENT);
        $this->getItem('/attachments/' . $attachmentId, ['attachment_read'], Response::HTTP_NOT_FOUND);
    }

    public function testAddAttachmentWithInvalidData(): void
    {
        $this->markTestSkippedByMinVersion('9.2.1');

        $response = $this->sendAttachment('/attachments', $this->getFormFields('Documentation', 'A product documentation file'), false, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'file', 'message' => 'This value should not be null.'],
        ], $response);
    }

    public function testNotFoundAttachment(): void
    {
        $this->markTestSkippedByMinVersion('9.2.1');

        $this->getItem('/attachments/999999', ['attachment_read'], Response::HTTP_NOT_FOUND);
        $this->sendAttachment('/attachments/999999', $this->getFormFields('Documentation', 'A product documentation file'), false, Response::HTTP_NOT_FOUND);
        $this->deleteItem('/attachments/999999', ['attachment_write'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteAttachments(): void
    {
        $this->markTestSkippedByMinVersion('9.2.1');

        $attachmentIds = [
            $this->createAttachment('Bulk attachment one'),
            $this->createAttachment('Bulk attachment two'),
            $this->createAttachment('Bulk attachment three'),
        ];

        $this->bulkDeleteItems('/attachments/bulk-delete', ['attachmentIds' => $attachmentIds], ['attachment_write']);

        foreach ($attachmentIds as $attachmentId) {
            $this->getItem('/attachments/' . $attachmentId, ['attachment_read'], Response::HTTP_NOT_FOUND);
        }

        // BulkDeleteAttachmentsHandler deletes the known ids and reports the unknown ones together at the end
        $attachmentId = $this->createAttachment('Bulk attachment four');
        $this->bulkDeleteItems('/attachments/bulk-delete', ['attachmentIds' => [$attachmentId, 999999]], ['attachment_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->getItem('/attachments/' . $attachmentId, ['attachment_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteAttachmentsValidationErrors(): void
    {
        $this->bulkDeleteItems('/attachments/bulk-delete', ['attachmentIds' => []], ['attachment_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->bulkDeleteItems('/attachments/bulk-delete', ['attachmentIds' => [0]], ['attachment_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'attachmentIds[0]', 'message' => 'This value should be positive.'],
        ], $response);
        $response = $this->bulkDeleteItems('/attachments/bulk-delete', ['attachmentIds' => ['3abc']], ['attachment_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'attachmentIds[0]', 'message' => 'This value should be of type integer.'],
        ], $response);
    }

    private function createAttachment(string $name): int
    {
        return $this->sendAttachment('/attachments', $this->getFormFields($name, 'A product documentation file'), true, Response::HTTP_CREATED)['attachmentId'];
    }

    /**
     * Sends the multipart POST of the creation and of the update.
     */
    private function sendAttachment(string $uri, array $parameters, bool $withFile, int $expectedHttpCode = Response::HTTP_OK): array
    {
        $extra = ['parameters' => $parameters];
        if ($withFile) {
            $extra['files'] = ['file' => $this->prepareUploadedFile(self::ASSET)];
        }

        return $this->requestApi('POST', $uri, null, ['attachment_write'], $expectedHttpCode, [
            'headers' => ['content-type' => 'multipart/form-data'],
            'extra' => $extra,
        ]);
    }

    private function getFormFields(string $name, string $description): array
    {
        return [
            'names' => ['en-US' => $name],
            'descriptions' => ['en-US' => $description],
        ];
    }

    private function expectedAttachment(int $attachmentId, string $name, string $description): array
    {
        return [
            'attachmentId' => $attachmentId,
            // The languages left out get the name of the default language
            'names' => ['en-US' => $name, 'fr-FR' => $name],
            'descriptions' => ['en-US' => $description, 'fr-FR' => ''],
            'fileName' => basename(self::ASSET),
        ];
    }
}
