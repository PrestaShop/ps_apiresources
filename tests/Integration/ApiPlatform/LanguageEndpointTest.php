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
use Tests\Resources\Resetter\LanguageResetter;

class LanguageEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['language_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset the languages (and the related tables) to the state they had before this test
        LanguageResetter::resetLanguages();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set status endpoint' => [
            'PATCH',
            '/languages/1/set-status',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/languages/1',
        ];

        yield 'bulk update status endpoint' => [
            'PUT',
            '/languages/bulk-update-status',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/languages/bulk-delete',
        ];
    }

    public function testSetStatus(): void
    {
        $languageId = self::addLanguageByLocale('es-ES');
        $this->assertTrue($this->getLanguageActiveStatus($languageId));

        $this->partialUpdateItem('/languages/' . $languageId . '/set-status', [
            'enabled' => false,
        ], ['language_write'], Response::HTTP_NO_CONTENT);
        $this->assertFalse($this->getLanguageActiveStatus($languageId));

        $this->partialUpdateItem('/languages/' . $languageId . '/set-status', [
            'enabled' => true,
        ], ['language_write'], Response::HTTP_NO_CONTENT);
        $this->assertTrue($this->getLanguageActiveStatus($languageId));
    }

    public function testSetStatusNotFound(): void
    {
        $this->partialUpdateItem('/languages/999999/set-status', [
            'enabled' => false,
        ], ['language_write'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkUpdateStatus(): void
    {
        $languageId1 = self::addLanguageByLocale('it-IT');
        $languageId2 = self::addLanguageByLocale('de-DE');

        $this->updateItem('/languages/bulk-update-status', [
            'languageIds' => [$languageId1, $languageId2],
            'enabled' => false,
        ], ['language_write'], Response::HTTP_NO_CONTENT);

        $this->assertFalse($this->getLanguageActiveStatus($languageId1));
        $this->assertFalse($this->getLanguageActiveStatus($languageId2));

        $this->updateItem('/languages/bulk-update-status', [
            'languageIds' => [$languageId1, $languageId2],
            'enabled' => true,
        ], ['language_write'], Response::HTTP_NO_CONTENT);

        $this->assertTrue($this->getLanguageActiveStatus($languageId1));
        $this->assertTrue($this->getLanguageActiveStatus($languageId2));
    }

    public function testDelete(): void
    {
        $languageId = self::addLanguageByLocale('pt-PT');
        $this->assertTrue(\Validate::isLoadedObject(new \Language($languageId)));

        $this->deleteItem('/languages/' . $languageId, ['language_write']);

        $this->assertFalse(\Validate::isLoadedObject(new \Language($languageId)));
    }

    public function testBulkDelete(): void
    {
        $languageId1 = self::addLanguageByLocale('nl-NL');
        $languageId2 = self::addLanguageByLocale('pl-PL');

        $this->bulkDeleteItems('/languages/bulk-delete', [
            'languageIds' => [$languageId1, $languageId2],
        ], ['language_write']);

        $this->assertFalse(\Validate::isLoadedObject(new \Language($languageId1)));
        $this->assertFalse(\Validate::isLoadedObject(new \Language($languageId2)));
    }

    private function getLanguageActiveStatus(int $languageId): bool
    {
        return (bool) (new \Language($languageId))->active;
    }
}
