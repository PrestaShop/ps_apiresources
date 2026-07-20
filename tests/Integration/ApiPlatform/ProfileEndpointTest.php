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

use Tests\Resources\DatabaseDump;

class ProfileEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['profile', 'profile_lang']);

        self::createApiClient(['profile_read', 'profile_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['profile', 'profile_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/profiles/1',
        ];

        yield 'post endpoint' => [
            'POST',
            '/profiles',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/profiles/1',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/profiles/bulk-delete',
        ];
    }

    public function testCreateProfile(): int
    {
        $response = $this->createItem('/profiles', [
            'names' => [
                'en-US' => 'Profile En',
                'fr-FR' => 'Profile Fr',
            ],
        ], ['profile_write']);

        $this->assertArrayHasKey('profileId', $response);
        $this->assertEquals([
            'profileId' => $response['profileId'],
            'names' => [
                'en-US' => 'Profile En',
                'fr-FR' => 'Profile Fr',
            ],
        ], $response);

        return $response['profileId'];
    }

    /**
     * @depends testCreateProfile
     */
    public function testGetProfile(int $profileId): int
    {
        $response = $this->getItem('/profiles/' . $profileId, ['profile_read']);

        $this->assertEquals([
            'profileId' => $profileId,
            'names' => [
                'en-US' => 'Profile En',
                'fr-FR' => 'Profile Fr',
            ],
        ], $response);

        return $profileId;
    }

    /**
     * @depends testGetProfile
     */
    public function testDeleteProfile(int $profileId): void
    {
        $this->deleteItem('/profiles/' . $profileId, ['profile_write']);
        $this->getItem('/profiles/' . $profileId, ['profile_read'], 404);
    }

    public function testBulkDeleteProfiles(): void
    {
        $firstProfileId = $this->createItem('/profiles', [
            'names' => [
                'en-US' => 'Bulk Profile 1 En',
                'fr-FR' => 'Bulk Profile 1 Fr',
            ],
        ], ['profile_write'])['profileId'];
        $secondProfileId = $this->createItem('/profiles', [
            'names' => [
                'en-US' => 'Bulk Profile 2 En',
                'fr-FR' => 'Bulk Profile 2 Fr',
            ],
        ], ['profile_write'])['profileId'];

        $this->bulkDeleteItems('/profiles/bulk-delete', [
            'profileIds' => [$firstProfileId, $secondProfileId],
        ], ['profile_write']);

        $this->getItem('/profiles/' . $firstProfileId, ['profile_read'], 404);
        $this->getItem('/profiles/' . $secondProfileId, ['profile_read'], 404);
    }
}
