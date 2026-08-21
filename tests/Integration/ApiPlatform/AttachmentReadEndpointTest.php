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

use PrestaShop\PrestaShop\Core\Context\LanguageContextBuilder;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Command\AddAttachmentCommand;
use PrestaShop\PrestaShop\Core\Domain\Attachment\ValueObject\AttachmentId;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\DummyFileUploader;

/**
 * Covers the read-only Attachment endpoints wiring the SearchAttachment,
 * GetAttachment and GetAttachmentInformation CQRS queries. The CRUD endpoints
 * (GET/POST/PATCH/DELETE) are handled by a separate resource (see PR #295) and
 * are intentionally not tested here.
 */
class AttachmentReadEndpointTest extends ApiTestCase
{
    private const SEARCHABLE_NAME = 'ApiResourceAttachment';

    private static int $attachmentId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['attachment_read']);

        self::$attachmentId = self::createAttachment(self::SEARCHABLE_NAME, 'test_text_file.txt');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
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
        yield 'search endpoint' => [
            'GET',
            '/attachments/search?phrase=test',
        ];

        yield 'get file endpoint' => [
            'GET',
            '/attachments/1/files',
        ];

        yield 'get information endpoint' => [
            'GET',
            '/attachments/1/information',
        ];
    }

    public function testSearchAttachments(): void
    {
        $defaultLocale = self::getDefaultLocale();

        $searchResults = $this->getItem('/attachments/search?phrase=' . self::SEARCHABLE_NAME, ['attachment_read']);

        $this->assertIsArray($searchResults);
        $this->assertNotEmpty($searchResults);

        $found = false;
        foreach ($searchResults as $result) {
            $this->assertArrayHasKey('attachmentId', $result);
            $this->assertArrayHasKey('names', $result);
            $this->assertArrayHasKey('fileName', $result);
            if ((int) $result['attachmentId'] === self::$attachmentId) {
                $found = true;
                $this->assertSame(self::SEARCHABLE_NAME, $result['names'][$defaultLocale]);
                $this->assertSame('test_text_file.txt', $result['fileName']);
            }
        }

        $this->assertTrue($found, 'The seeded attachment should be returned by the search.');
    }

    public function testSearchAttachmentsWithNoResults(): void
    {
        $this->getItem('/attachments/search?phrase=nonexistentattachment999', ['attachment_read'], Response::HTTP_NOT_FOUND);
    }

    public function testSearchAttachmentsWithEmptyPhrase(): void
    {
        $this->getItem('/attachments/search?phrase=', ['attachment_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetAttachmentFile(): void
    {
        $attachmentFile = $this->getItem('/attachments/' . self::$attachmentId . '/files', ['attachment_read']);

        $this->assertEquals(self::$attachmentId, $attachmentFile['attachmentId']);
        $this->assertSame('test_text_file.txt', $attachmentFile['name']);
        $this->assertNotEmpty($attachmentFile['path']);
        $this->assertFileExists($attachmentFile['path']);
    }

    public function testGetNotFoundAttachmentFile(): void
    {
        $this->getItem('/attachments/999999/files', ['attachment_read'], Response::HTTP_NOT_FOUND);
    }

    public function testGetAttachmentInformation(): void
    {
        $defaultLocale = self::getDefaultLocale();

        $information = $this->getItem('/attachments/' . self::$attachmentId . '/information', ['attachment_read']);

        $this->assertEquals(self::$attachmentId, $information['attachmentId']);
        $this->assertSame(self::SEARCHABLE_NAME, $information['names'][$defaultLocale]);
        $this->assertArrayHasKey('descriptions', $information);
        $this->assertSame('test_text_file.txt', $information['fileName']);
        $this->assertSame('text/plain', $information['mimeType']);
        $this->assertGreaterThan(0, $information['fileSize']);
    }

    public function testGetNotFoundAttachmentInformation(): void
    {
        $this->getItem('/attachments/999999/information', ['attachment_read'], Response::HTTP_NOT_FOUND);
    }

    private static function createAttachment(string $name, string $dummyFileName): int
    {
        $defaultLangId = (int) \Configuration::get('PS_LANG_DEFAULT');

        $container = static::createClient()->getContainer();

        // The AddAttachmentCommand validates the localized names against the default language,
        // which requires the language context to be built. Outside of an HTTP request it must
        // be defined explicitly.
        /** @var LanguageContextBuilder $languageContextBuilder */
        $languageContextBuilder = $container->get('test_language_context_builder');
        $languageContextBuilder->setLanguageId($defaultLangId);
        $languageContextBuilder->setDefaultLanguageId($defaultLangId);

        $sourceFile = DummyFileUploader::upload($dummyFileName);

        $command = new AddAttachmentCommand(
            [$defaultLangId => $name],
            [$defaultLangId => 'Description of ' . $name]
        );
        $command->setFileInformation(
            $sourceFile,
            (int) filesize($sourceFile),
            (string) mime_content_type($sourceFile),
            $dummyFileName
        );

        /** @var AttachmentId $attachmentId */
        $attachmentId = $container->get('prestashop.core.command_bus')->handle($command);
        $attachmentId = $attachmentId->getValue();

        // The core file uploader relies on move_uploaded_file(), which is a no-op outside a
        // real HTTP upload, so the physical file is never stored. Copy it to the location
        // GetAttachment reads from so the /files endpoint can be exercised.
        $attachment = new \Attachment($attachmentId);
        copy(DummyFileUploader::getDummyFilePath($dummyFileName), _PS_DOWNLOAD_DIR_ . $attachment->file);

        return $attachmentId;
    }

    private static function getDefaultLocale(): string
    {
        $defaultLangId = (int) \Configuration::get('PS_LANG_DEFAULT');

        return (new \Language($defaultLangId))->getLocale();
    }
}
