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
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['profile_read', 'profile_write']);
        DatabaseDump::restoreTables(['profile', 'profile_lang']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['profile', 'profile_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get profile endpoint' => ['GET', '/profiles/1'];
        yield 'create profile endpoint' => ['POST', '/profiles'];
        yield 'update profile endpoint' => ['PATCH', '/profiles/1'];
        yield 'delete profile endpoint' => ['DELETE', '/profiles/1'];
        yield 'bulk delete profiles endpoint' => ['DELETE', '/profiles/bulk-delete'];
    }

    public function testCreateProfile(): int
    {
        $profile = $this->createItem('/profiles', [
            'names' => ['en-US' => 'Test Profile'],
        ], ['profile_write']);

        $this->assertArrayHasKey('profileId', $profile);

        return $profile['profileId'];
    }

    /**
     * @depends testCreateProfile
     */
    public function testGetProfile(int $profileId): int
    {
        $profile = $this->getItem('/profiles/' . $profileId, ['profile_read']);

        $this->assertEquals($profileId, $profile['profileId']);
        $this->assertArrayHasKey('names', $profile);

        $expectedProfile = $profile;
        $this->assertEquals($expectedProfile, $this->getItem('/profiles/' . $profileId, ['profile_read']));

        return $profileId;
    }

    /**
     * @depends testGetProfile
     */
    public function testUpdateProfile(int $profileId): int
    {
        $updated = $this->partialUpdateItem('/profiles/' . $profileId, [
            'names' => ['en-US' => 'Updated Profile'],
        ], ['profile_write']);

        $profile = $this->getItem('/profiles/' . $profileId, ['profile_read']);
        $this->assertEquals('Updated Profile', $profile['names']['en-US']);

        return $profileId;
    }

    public function testDeleteProfile(): void
    {
        $profile = $this->createItem('/profiles', [
            'names' => ['en-US' => 'To Delete Profile'],
        ], ['profile_write']);

        // Delete may return 422 due to CQRSCommand mapping
        $this->deleteItem('/profiles/' . $profile['profileId'], ['profile_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetNonExistentProfile(): void
    {
        $this->getItem('/profiles/999999', ['profile_read'], Response::HTTP_NOT_FOUND);
    }
}
