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
use Tests\Resources\Resetter\LanguageResetter;

class LanguageEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['lang', 'lang_shop']);
        self::createApiClient(['language_read', 'language_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['lang', 'lang_shop']);
        // Reset the languages (and the related tables) to the state they had before this test
        LanguageResetter::resetLanguages();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => ['GET', '/languages/1/details'];
        yield 'create endpoint' => ['POST', '/languages'];
        yield 'update endpoint' => ['PATCH', '/languages/1'];
        yield 'set status endpoint' => ['PATCH', '/languages/1/set-status'];
        yield 'delete endpoint' => ['DELETE', '/languages/1'];
        yield 'bulk update status endpoint' => ['PUT', '/languages/bulk-update-status'];
        yield 'bulk delete endpoint' => ['DELETE', '/languages/bulk-delete'];
    }

    /**
     * Every language these tests operate on is created through POST /languages. The status and
     * delete tests used the addLanguageByLocale() command-bus helper because the create
     * endpoint lived in another PR.
     *
     * AddLanguageHandler validates and copies both image paths, so they must point at a real
     * image. _PS_IMG_DIR_ is the test fixture image directory and does not ship l/en.jpg, so
     * the flag is generated once in the system temp directory instead.
     */
    private function createLanguage(string $isoCode, bool $enabled = true): array
    {
        $flag = self::createFlagImage();

        return $this->createItem('/languages', [
            'name' => 'Test ' . strtoupper($isoCode),
            'isoCode' => $isoCode,
            'tagIETF' => $isoCode . '-' . strtoupper($isoCode),
            'shortDateFormat' => 'Y-m-d',
            'fullDateFormat' => 'Y-m-d H:i:s',
            'flagImagePath' => $flag,
            'noPictureImagePath' => $flag,
            'rtl' => false,
            'enabled' => $enabled,
            'shopIds' => [1],
        ], ['language_write'], Response::HTTP_CREATED);
    }

    private static function createFlagImage(): string
    {
        static $path = null;

        if (null === $path) {
            $path = sys_get_temp_dir() . '/ps-api-language-flag.jpg';
            $image = imagecreatetruecolor(16, 11);
            imagefill($image, 0, 0, imagecolorallocate($image, 0, 0, 128));
            imagejpeg($image, $path);
            imagedestroy($image);
        }

        return $path;
    }

    private function getLanguage(int $languageId): array
    {
        return $this->getItem('/languages/' . $languageId . '/details', ['language_read']);
    }

    private function isLanguageEnabled(int $languageId): bool
    {
        return (bool) $this->getLanguage($languageId)['enabled'];
    }

    public function testCreateLanguage(): int
    {
        $language = $this->createLanguage('ts');

        $this->assertSame('Test TS', $language['name']);
        $this->assertSame('ts', $language['isoCode']);
        $this->assertTrue($language['enabled']);
        // The create replays GetLanguageForEditing, so it answers exactly what the GET does
        $this->assertEquals($this->getLanguage($language['languageId']), $language);

        return (int) $language['languageId'];
    }

    /**
     * @depends testCreateLanguage
     */
    public function testEditLanguage(int $languageId): void
    {
        // Asserted through the API instead of
        // SELECT name FROM ps_lang WHERE id_lang = ...
        $updated = $this->partialUpdateItem(
            '/languages/' . $languageId,
            ['name' => 'RenamedTest'],
            ['language_write']
        );

        $this->assertSame('RenamedTest', $updated['name']);
        $this->assertEquals($this->getLanguage($languageId), $updated);
    }

    public function testEditUnknownLanguageReturnsNotFound(): void
    {
        $this->partialUpdateItem(
            '/languages/999999',
            ['name' => 'Whatever'],
            ['language_write'],
            Response::HTTP_NOT_FOUND
        );
    }

    public function testSetStatus(): void
    {
        $languageId = (int) $this->createLanguage('tu')['languageId'];
        $this->assertTrue($this->isLanguageEnabled($languageId));

        $this->partialUpdateItem('/languages/' . $languageId . '/set-status', [
            'enabled' => false,
        ], ['language_write'], Response::HTTP_NO_CONTENT);
        $this->assertFalse($this->isLanguageEnabled($languageId));

        $this->partialUpdateItem('/languages/' . $languageId . '/set-status', [
            'enabled' => true,
        ], ['language_write'], Response::HTTP_NO_CONTENT);
        $this->assertTrue($this->isLanguageEnabled($languageId));
    }

    public function testSetStatusNotFound(): void
    {
        $this->partialUpdateItem('/languages/999999/set-status', [
            'enabled' => false,
        ], ['language_write'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkUpdateStatus(): void
    {
        $languageIds = [
            (int) $this->createLanguage('tv')['languageId'],
            (int) $this->createLanguage('tw')['languageId'],
        ];

        $this->updateItem('/languages/bulk-update-status', [
            'languageIds' => $languageIds,
            'enabled' => false,
        ], ['language_write'], Response::HTTP_NO_CONTENT);
        foreach ($languageIds as $languageId) {
            $this->assertFalse($this->isLanguageEnabled($languageId));
        }

        $this->updateItem('/languages/bulk-update-status', [
            'languageIds' => $languageIds,
            'enabled' => true,
        ], ['language_write'], Response::HTTP_NO_CONTENT);
        foreach ($languageIds as $languageId) {
            $this->assertTrue($this->isLanguageEnabled($languageId));
        }
    }

    public function testDelete(): void
    {
        $languageId = (int) $this->createLanguage('tx')['languageId'];

        $this->deleteItem('/languages/' . $languageId, ['language_write']);

        // Asserted through the API instead of Validate::isLoadedObject(new Language($id))
        $this->getItem('/languages/' . $languageId . '/details', ['language_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDelete(): void
    {
        $languageIds = [
            (int) $this->createLanguage('ty')['languageId'],
            (int) $this->createLanguage('tz')['languageId'],
        ];

        $this->bulkDeleteItems('/languages/bulk-delete', [
            'languageIds' => $languageIds,
        ], ['language_write']);

        foreach ($languageIds as $languageId) {
            $this->getItem('/languages/' . $languageId . '/details', ['language_read'], Response::HTTP_NOT_FOUND);
        }
    }
}
