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

use PrestaShop\PrestaShop\Adapter\Language\LanguageImageManager;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class LanguageEndpointTest extends ApiTestCase
{
    /**
     * Languages created by the tests, by id, with their ISO code: their images survive the database restore
     *
     * @var array<int, string>
     */
    protected static array $createdLanguages = [];

    public static function setUpBeforeClass(): void
    {
        // Every operation of the domain requires PrestaShop 9.3.0 (PrestaShop/PrestaShop#42852)
        if (self::isVersionUnder('9.3.0')) {
            static::markTestSkipped('The language endpoints require PrestaShop 9.3.0');

            return;
        }

        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['lang', 'lang_shop']);
        self::createApiClient(['language_read', 'language_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        $imageManager = new LanguageImageManager();
        foreach (self::$createdLanguages as $languageId => $isoCode) {
            $imageManager->deleteImages($languageId, $isoCode);
        }
        self::$createdLanguages = [];
        DatabaseDump::restoreTables(['lang', 'lang_shop']);
        LanguageResetter::resetLanguages();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => ['GET', '/languages/1'];
        yield 'create endpoint' => ['POST', '/languages', 'multipart/form-data'];
        yield 'update with images endpoint' => ['POST', '/languages/1', 'multipart/form-data'];
        yield 'update endpoint' => ['PATCH', '/languages/1'];
        yield 'set status endpoint' => ['PATCH', '/languages/1/set-status'];
        yield 'delete endpoint' => ['DELETE', '/languages/1'];
        yield 'bulk update status endpoint' => ['PUT', '/languages/bulk-update-status'];
        yield 'bulk delete endpoint' => ['DELETE', '/languages/bulk-delete'];
    }

    /**
     * Form values of a multipart creation: all strings, booleans as 1 / 0
     */
    private function getCreateParameters(string $isoCode, string $tagIETF): array
    {
        return [
            'name' => 'Test ' . strtoupper($isoCode),
            'isoCode' => $isoCode,
            'tagIETF' => $tagIETF,
            'shortDateFormat' => 'Y-m-d',
            'fullDateFormat' => 'Y-m-d H:i:s',
            'rtl' => '0',
            'enabled' => '1',
            'shopIds' => ['1'],
        ];
    }

    private function getMultipartOptions(array $parameters, array $files): array
    {
        return [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => $parameters,
                'files' => $files,
            ],
        ];
    }

    private function getImageFiles(): array
    {
        // Two different assets: the API removes each uploaded file, so they cannot share a temporary copy
        return [
            'flagImage' => $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/lang/en.jpg'),
            'noPictureImage' => $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/lang/fr.jpg'),
        ];
    }

    private function createLanguage(string $isoCode, string $tagIETF, array $parameters = []): array
    {
        $language = $this->requestApi('POST', '/languages', null, ['language_write'], Response::HTTP_CREATED, $this->getMultipartOptions(
            array_merge($this->getCreateParameters($isoCode, $tagIETF), $parameters),
            $this->getImageFiles()
        ));
        self::$createdLanguages[$language['languageId']] = $isoCode;

        return $language;
    }

    private function getExpectedLanguage(int $languageId, string $isoCode, string $tagIETF, string $locale, array $data = []): array
    {
        return array_merge([
            'languageId' => $languageId,
            'name' => 'Test ' . strtoupper($isoCode),
            'isoCode' => $isoCode,
            'tagIETF' => $tagIETF,
            // Derived by the core from the ISO code
            'locale' => $locale,
            'shortDateFormat' => 'Y-m-d',
            'fullDateFormat' => 'Y-m-d H:i:s',
            'rtl' => false,
            'enabled' => true,
            'shopIds' => [1],
        ], $data);
    }

    private function getLanguage(int $languageId): array
    {
        return $this->getItem('/languages/' . $languageId, ['language_read']);
    }

    public function testCreateLanguage(): int
    {
        $language = $this->createLanguage('nl', 'nl-nl');
        $this->assertArrayHasKey('languageId', $language);
        $languageId = (int) $language['languageId'];

        // The form values are all strings, but the returned language uses the real field types, and the images are
        // not part of the payload
        $expected = $this->getExpectedLanguage($languageId, 'nl', 'nl-nl', 'nl-NL');
        $this->assertEquals($expected, $language);
        $this->assertEquals($expected, $this->getLanguage($languageId));

        // The images are stored: the flag by language id, the "no picture" image by ISO code
        $this->assertFileExists(_PS_IMG_DIR_ . 'l/' . $languageId . '.jpg');
        $this->assertFileExists(_PS_IMG_DIR_ . 'p/nl.jpg');
        $this->assertFileExists(_PS_IMG_DIR_ . 'c/nl.jpg');
        $this->assertFileExists(_PS_IMG_DIR_ . 'm/nl.jpg');

        return $languageId;
    }

    public function testCreateLanguageWithDefaultValues(): void
    {
        $parameters = $this->getCreateParameters('pl', 'pl-pl');
        unset($parameters['rtl'], $parameters['enabled'], $parameters['shopIds']);

        $language = $this->requestApi('POST', '/languages', null, ['language_write'], Response::HTTP_CREATED, $this->getMultipartOptions(
            $parameters,
            $this->getImageFiles()
        ));
        self::$createdLanguages[$language['languageId']] = 'pl';

        // The defaults of the BO form: not RTL, enabled, the shop of the context
        $this->assertEquals($this->getExpectedLanguage((int) $language['languageId'], 'pl', 'pl-pl', 'pl-PL'), $language);
    }

    public function testCreateLanguageAsJsonIsRejected(): void
    {
        // The images are required, so the creation only accepts multipart form data
        $this->createItem('/languages', [
            'name' => 'Test JSON',
            'isoCode' => 'pt',
            'tagIETF' => 'pt-pt',
            'shortDateFormat' => 'Y-m-d',
            'fullDateFormat' => 'Y-m-d H:i:s',
        ], ['language_write'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
    }

    /**
     * @depends testCreateLanguage
     */
    public function testPartialUpdateLanguage(int $languageId): int
    {
        $patchData = [
            'name' => 'Renamed test',
            'shortDateFormat' => 'd-m-Y',
            'fullDateFormat' => 'd-m-Y H:i:s',
        ];
        $expected = $this->getExpectedLanguage($languageId, 'nl', 'nl-nl', 'nl-NL', $patchData);

        $updated = $this->partialUpdateItem('/languages/' . $languageId, $patchData, ['language_write']);
        $this->assertEquals($expected, $updated);
        $this->assertEquals($expected, $this->getLanguage($languageId));

        return $languageId;
    }

    /**
     * @depends testPartialUpdateLanguage
     */
    public function testUpdateLanguageWithImages(int $languageId): int
    {
        $flagPath = _PS_IMG_DIR_ . 'l/' . $languageId . '.jpg';
        $noPicturePath = _PS_IMG_DIR_ . 'p/nl.jpg';
        $previousFlag = md5_file($flagPath);
        $previousNoPicture = md5_file($noPicturePath);

        // The images are replaced by a multipart update, which updates the other fields of the payload at the same time
        $updated = $this->requestApi('POST', '/languages/' . $languageId, null, ['language_write'], Response::HTTP_OK, $this->getMultipartOptions(
            ['name' => 'Updated with images'],
            [
                // Swapped compared to the creation, so both stored images change
                'flagImage' => $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/lang/fr.jpg'),
                'noPictureImage' => $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/lang/en.jpg'),
            ]
        ));
        $expected = $this->getExpectedLanguage($languageId, 'nl', 'nl-nl', 'nl-NL', [
            'name' => 'Updated with images',
            'shortDateFormat' => 'd-m-Y',
            'fullDateFormat' => 'd-m-Y H:i:s',
        ]);
        $this->assertEquals($expected, $updated);
        $this->assertEquals($expected, $this->getLanguage($languageId));

        clearstatcache();
        $this->assertNotEquals($previousFlag, md5_file($flagPath));
        $this->assertNotEquals($previousNoPicture, md5_file($noPicturePath));

        // Without images, the multipart update keeps the stored ones
        $currentFlag = md5_file($flagPath);
        $expected['name'] = 'Updated without images';
        $this->assertEquals($expected, $this->requestApi('POST', '/languages/' . $languageId, null, ['language_write'], Response::HTTP_OK, $this->getMultipartOptions(
            ['name' => 'Updated without images'],
            []
        )));
        clearstatcache();
        $this->assertEquals($currentFlag, md5_file($flagPath));

        return $languageId;
    }

    /**
     * @depends testUpdateLanguageWithImages
     */
    public function testSetStatus(int $languageId): int
    {
        $expected = $this->getExpectedLanguage($languageId, 'nl', 'nl-nl', 'nl-NL', [
            'name' => 'Updated without images',
            'shortDateFormat' => 'd-m-Y',
            'fullDateFormat' => 'd-m-Y H:i:s',
            'enabled' => false,
        ]);
        $this->assertEquals($expected, $this->partialUpdateItem('/languages/' . $languageId . '/set-status', [
            'enabled' => false,
        ], ['language_write']));
        $this->assertEquals($expected, $this->getLanguage($languageId));

        $expected['enabled'] = true;
        $this->assertEquals($expected, $this->partialUpdateItem('/languages/' . $languageId . '/set-status', [
            'enabled' => true,
        ], ['language_write']));
        $this->assertEquals($expected, $this->getLanguage($languageId));

        return $languageId;
    }

    /**
     * @depends testSetStatus
     */
    public function testDeleteLanguage(int $languageId): void
    {
        $this->deleteItem('/languages/' . $languageId, ['language_write']);
        $this->getItem('/languages/' . $languageId, ['language_read'], Response::HTTP_NOT_FOUND);

        // The core removes the images of a deleted language
        $this->assertFileDoesNotExist(_PS_IMG_DIR_ . 'l/' . $languageId . '.jpg');
        $this->assertFileDoesNotExist(_PS_IMG_DIR_ . 'p/nl.jpg');
        unset(self::$createdLanguages[$languageId]);
    }

    public function testUnknownLanguage(): void
    {
        $this->getItem('/languages/999999', ['language_read'], Response::HTTP_NOT_FOUND);
        $this->partialUpdateItem('/languages/999999', ['name' => 'Whatever'], ['language_write'], Response::HTTP_NOT_FOUND);
        $this->partialUpdateItem('/languages/999999/set-status', ['enabled' => false], ['language_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/languages/999999', ['language_write'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkUpdateStatus(): void
    {
        $languages = [
            $this->createLanguage('sv', 'sv-se'),
            $this->createLanguage('da', 'da-dk'),
        ];
        $languageIds = array_map(static fn (array $language): int => (int) $language['languageId'], $languages);

        $this->updateItem('/languages/bulk-update-status', [
            'languageIds' => $languageIds,
            'enabled' => false,
        ], ['language_write'], Response::HTTP_NO_CONTENT);
        foreach ($languages as $language) {
            $this->assertEquals(
                array_merge($language, ['enabled' => false]),
                $this->getLanguage((int) $language['languageId'])
            );
        }

        $this->updateItem('/languages/bulk-update-status', [
            'languageIds' => $languageIds,
            'enabled' => true,
        ], ['language_write'], Response::HTTP_NO_CONTENT);
        foreach ($languages as $language) {
            $this->assertEquals($language, $this->getLanguage((int) $language['languageId']));
        }
    }

    public function testBulkDelete(): void
    {
        $languageIds = [
            (int) $this->createLanguage('fi', 'fi-fi')['languageId'],
            (int) $this->createLanguage('cs', 'cs-cz')['languageId'],
        ];

        $this->bulkDeleteItems('/languages/bulk-delete', [
            'languageIds' => $languageIds,
        ], ['language_write']);

        foreach ($languageIds as $languageId) {
            $this->getItem('/languages/' . $languageId, ['language_read'], Response::HTTP_NOT_FOUND);
            unset(self::$createdLanguages[$languageId]);
        }
    }

    public function testInvalidCreate(): void
    {
        // Every field breaks the rules of the BO form, and the "no picture" image is missing
        $validationErrorsResponse = $this->requestApi('POST', '/languages', null, ['language_write'], Response::HTTP_UNPROCESSABLE_ENTITY, $this->getMultipartOptions(
            [
                'name' => '<b>Invalid</b>',
                'isoCode' => 'a1',
                'tagIETF' => 'not a tag',
                'shortDateFormat' => '<Y>',
                'fullDateFormat' => '',
            ],
            [
                'flagImage' => $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/archive/test_install_cqrs_command.zip'),
            ]
        ));
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            ['propertyPath' => 'name'],
            ['propertyPath' => 'isoCode'],
            ['propertyPath' => 'tagIETF'],
            ['propertyPath' => 'shortDateFormat'],
            ['propertyPath' => 'fullDateFormat'],
            ['propertyPath' => 'flagImage'],
            ['propertyPath' => 'noPictureImage'],
        ], $validationErrorsResponse);
    }

    public function testInvalidUpdate(): void
    {
        $languageId = (int) $this->createLanguage('hu', 'hu-hu')['languageId'];

        $validationErrorsResponse = $this->partialUpdateItem('/languages/' . $languageId, [
            'name' => '<b>Invalid</b>',
            'isoCode' => 'a1',
            'shortDateFormat' => '<Y>',
        ], ['language_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            ['propertyPath' => 'name'],
            ['propertyPath' => 'isoCode'],
            ['propertyPath' => 'shortDateFormat'],
        ], $validationErrorsResponse);

        // An image that is not one is refused on the multipart update as well
        $validationErrorsResponse = $this->requestApi('POST', '/languages/' . $languageId, null, ['language_write'], Response::HTTP_UNPROCESSABLE_ENTITY, $this->getMultipartOptions(
            [],
            ['noPictureImage' => $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/archive/test_install_cqrs_command.zip')]
        ));
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            ['propertyPath' => 'noPictureImage'],
        ], $validationErrorsResponse);
    }

    public function testAddExistingIsoCode(): void
    {
        $this->createLanguage('el', 'el-gr');

        // LanguageConstraintException::DUPLICATE_ISO_CODE
        $this->requestApi('POST', '/languages', null, ['language_write'], Response::HTTP_UNPROCESSABLE_ENTITY, $this->getMultipartOptions(
            $this->getCreateParameters('el', 'el-gr'),
            $this->getImageFiles()
        ));
    }
}
