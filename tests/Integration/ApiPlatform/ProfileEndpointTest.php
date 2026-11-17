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

class ProfileEndpointTest extends ApiTestCase
{
    private const SUPER_ADMIN_PROFILE_ID = 1;

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

        yield 'patch endpoint' => [
            'PATCH',
            '/profiles/1',
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
    public function testEditProfile(int $profileId): int
    {
        $response = $this->partialUpdateItem('/profiles/' . $profileId, [
            'names' => [
                'en-US' => 'Profile En Updated',
                'fr-FR' => 'Profile Fr Updated',
            ],
        ], ['profile_write']);

        $this->assertEquals([
            'profileId' => $profileId,
            'names' => [
                'en-US' => 'Profile En Updated',
                'fr-FR' => 'Profile Fr Updated',
            ],
        ], $response);

        return $profileId;
    }

    /**
     * @depends testEditProfile
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

    public function testNonExistentProfile(): void
    {
        $this->getItem('/profiles/99999', ['profile_read'], Response::HTTP_NOT_FOUND);
        $this->partialUpdateItem('/profiles/99999', [
            'names' => [
                'en-US' => 'Profile En',
                'fr-FR' => 'Profile Fr',
            ],
        ], ['profile_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/profiles/99999', ['profile_write'], Response::HTTP_NOT_FOUND);
        $this->bulkDeleteItems('/profiles/bulk-delete', ['profileIds' => [99999]], ['profile_write'], Response::HTTP_NOT_FOUND);
    }

    public function testInvalidProfile(): void
    {
        $response = $this->createItem('/profiles', [
            'names' => [
                'fr-FR' => 'Invalid<',
            ],
        ], ['profile_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'names',
                'message' => 'The field names is required at least in your default language.',
            ],
            [
                'propertyPath' => 'names[fr-FR]',
                'message' => '"Invalid<" is invalid',
            ],
        ], $response);

        $profileId = $this->createItem('/profiles', [
            'names' => [
                'en-US' => 'Profile To Keep En',
                'fr-FR' => 'Profile To Keep Fr',
            ],
        ], ['profile_write'])['profileId'];

        $response = $this->partialUpdateItem('/profiles/' . $profileId, [
            'names' => [
                'en-US' => '',
                'fr-FR' => 'Profile Fr',
            ],
        ], ['profile_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'names',
                'message' => 'The field names is required at least in your default language.',
            ],
        ], $response);

        // DefaultLanguage skips an update without the default language key, EditProfileHandler then refuses the
        // name array it would replace whole (ProfileException, mapped without field list)
        $this->partialUpdateItem('/profiles/' . $profileId, [
            'names' => [
                'fr-FR' => 'Profile Fr Only',
            ],
        ], ['profile_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertEquals([
            'profileId' => $profileId,
            'names' => [
                'en-US' => 'Profile To Keep En',
                'fr-FR' => 'Profile To Keep Fr',
            ],
        ], $this->getItem('/profiles/' . $profileId, ['profile_read']));
    }

    public function testSuperAdminProfileCannotBeDeleted(): void
    {
        $this->deleteItem('/profiles/' . self::SUPER_ADMIN_PROFILE_ID, ['profile_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->bulkDeleteItems('/profiles/bulk-delete', [
            'profileIds' => [self::SUPER_ADMIN_PROFILE_ID],
        ], ['profile_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->getItem('/profiles/' . self::SUPER_ADMIN_PROFILE_ID, ['profile_read']);
    }

    public function testBulkDeleteProfilesWithInvalidIds(): void
    {
        $this->bulkDeleteItems('/profiles/bulk-delete', ['profileIds' => []], ['profile_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // The command casts each id with (int): "3abc" would delete the profile 3
        $response = $this->bulkDeleteItems('/profiles/bulk-delete', ['profileIds' => [0, '3abc']], ['profile_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'profileIds[0]', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'profileIds[1]', 'message' => 'This value should be of type integer.'],
        ], $response);
    }
}
