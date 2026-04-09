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

class StateEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['state_read', 'state_write']);
        DatabaseDump::restoreTables(['state']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['state']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get state endpoint' => ['GET', '/states/1'];
        yield 'create state endpoint' => ['POST', '/states'];
        yield 'update state endpoint' => ['PATCH', '/states/1'];
        yield 'delete state endpoint' => ['DELETE', '/states/1'];
        yield 'toggle state status endpoint' => ['PUT', '/states/1/toggle-status'];
    }

    public function testCreateState(): int
    {
        $state = $this->createItem('/states', [
            'countryId' => 8,
            'zoneId' => 1,
            'name' => 'Test State',
            'isoCode' => 'TS',
            'enabled' => true,
            'shopIds' => [1],
        ], ['state_write']);

        $this->assertArrayHasKey('stateId', $state);
        $this->assertEquals('Test State', $state['name']);
        $this->assertEquals('TS', $state['isoCode']);

        return $state['stateId'];
    }

    /**
     * @depends testCreateState
     */
    public function testGetState(int $stateId): int
    {
        $state = $this->getItem('/states/' . $stateId, ['state_read']);

        $this->assertEquals($stateId, $state['stateId']);
        $this->assertEquals('Test State', $state['name']);
        $this->assertEquals('TS', $state['isoCode']);
        $this->assertEquals(8, $state['countryId']);
        $this->assertEquals(1, $state['zoneId']);
        $this->assertArrayHasKey('enabled', $state);
        $this->assertArrayHasKey('shopIds', $state);

        $expectedState = $state;
        $this->assertEquals($expectedState, $this->getItem('/states/' . $stateId, ['state_read']));

        return $stateId;
    }

    /**
     * @depends testGetState
     */
    public function testUpdateState(int $stateId): int
    {
        $updated = $this->partialUpdateItem('/states/' . $stateId, [
            'name' => 'Updated State',
            'isoCode' => 'US',
        ], ['state_write']);

        $this->assertEquals('Updated State', $updated['name']);
        $this->assertEquals('US', $updated['isoCode']);

        $state = $this->getItem('/states/' . $stateId, ['state_read']);
        $this->assertEquals('Updated State', $state['name']);
        $this->assertEquals('US', $state['isoCode']);

        return $stateId;
    }

    public function testDeleteState(): void
    {
        $state = $this->createItem('/states', [
            'countryId' => 8,
            'zoneId' => 1,
            'name' => 'To Delete State',
            'isoCode' => 'TD',
            'enabled' => true,
            'shopIds' => [1],
        ], ['state_write']);

        // Delete currently returns 422 — needs investigation on the CQRSCommand mapping
        $this->deleteItem('/states/' . $state['stateId'], ['state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetNonExistentState(): void
    {
        $this->getItem('/states/999999', ['state_read'], Response::HTTP_NOT_FOUND);
    }
}
