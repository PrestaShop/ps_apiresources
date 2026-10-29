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
    /**
     * New York, used by two addresses of the demo data, so it cannot be deleted.
     */
    private const STATE_USED_BY_AN_ADDRESS = 35;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['state_read', 'state_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['state']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/states'];
        yield 'get endpoint' => ['GET', '/states/1'];
        yield 'update endpoint' => ['PATCH', '/states/1'];
        yield 'delete endpoint' => ['DELETE', '/states/1'];
    }

    public function testAddState(): int
    {
        $state = $this->createItem('/states', $this->getCreatePayload(), ['state_write']);
        $this->assertArrayHasKey('stateId', $state);
        $this->assertEquals($this->expectedState($state['stateId']), $state);

        return $state['stateId'];
    }

    /**
     * @depends testAddState
     */
    public function testGetState(int $stateId): int
    {
        $this->assertEquals($this->expectedState($stateId), $this->getItem('/states/' . $stateId, ['state_read']));

        return $stateId;
    }

    /**
     * @depends testGetState
     */
    public function testUpdateState(int $stateId): int
    {
        $patchData = [
            'name' => 'Updated Test State',
            'zoneId' => 1,
            'isoCode' => 'XY',
            'enabled' => false,
        ];
        $expected = $this->expectedState($stateId, $patchData);

        $this->assertEquals($expected, $this->partialUpdateItem('/states/' . $stateId, $patchData, ['state_write']));
        $this->assertEquals($expected, $this->getItem('/states/' . $stateId, ['state_read']));

        return $stateId;
    }

    /**
     * @depends testUpdateState
     */
    public function testDeleteState(int $stateId): void
    {
        // This endpoint returns an empty response and a 204 HTTP code.
        $this->assertNull($this->deleteItem('/states/' . $stateId, ['state_write']));

        $this->getItem('/states/' . $stateId, ['state_read'], Response::HTTP_NOT_FOUND);
    }

    public function testNotFoundState(): void
    {
        $this->getItem('/states/999999', ['state_read'], Response::HTTP_NOT_FOUND);
        $this->partialUpdateItem('/states/999999', ['enabled' => true], ['state_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/states/999999', ['state_write'], Response::HTTP_NOT_FOUND);
    }

    public function testInvalidStateId(): void
    {
        // The id passes the \d+ requirement and is refused by the StateId value object: a mapped exception, no field list
        $this->getItem('/states/0', ['state_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->partialUpdateItem('/states/0', ['enabled' => true], ['state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        // DeleteStateCommand turns 0 into a NoStateId, which the handler reports as not found
        $this->deleteItem('/states/0', ['state_write'], Response::HTTP_NOT_FOUND);
    }

    public function testAddStateWithInvalidData(): void
    {
        $response = $this->createItem('/states', $this->getCreatePayload(['name' => '']), ['state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'name', 'message' => 'This value should not be blank.'],
        ], $response);
    }

    public function testAddStateWithInvalidValues(): void
    {
        $response = $this->createItem('/states', $this->getCreatePayload([
            'name' => str_repeat('a', 65),
            'isoCode' => 'ABCDEFGH',
            'countryId' => 0,
            'zoneId' => 0,
        ]), ['state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'name', 'message' => 'This value is too long. It should have 64 characters or less.'],
            // Too long, and refused by the typed regex
            ['propertyPath' => 'isoCode'],
            ['propertyPath' => 'countryId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'zoneId', 'message' => 'This value should be positive.'],
        ], $response);

        // Refused by the typed regex constraints: the message carries the value, only the status is asserted
        foreach ([['name' => '<b>'], ['isoCode' => 'ABCDEF']] as $overrides) {
            $this->createItem('/states', $this->getCreatePayload($overrides), ['state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function testEditStateWithInvalidValues(): void
    {
        $stateId = $this->createItem('/states', $this->getCreatePayload(), ['state_write'])['stateId'];

        $response = $this->partialUpdateItem('/states/' . $stateId, [
            'name' => str_repeat('a', 65),
            'countryId' => 0,
            'zoneId' => 0,
        ], ['state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'name', 'message' => 'This value is too long. It should have 64 characters or less.'],
            ['propertyPath' => 'countryId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'zoneId', 'message' => 'This value should be positive.'],
        ], $response);
        foreach ([['name' => '<b>'], ['isoCode' => 'ABCDEF']] as $patchData) {
            $this->partialUpdateItem('/states/' . $stateId, $patchData, ['state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->assertEquals($this->expectedState($stateId), $this->getItem('/states/' . $stateId, ['state_read']));

        $this->deleteItem('/states/' . $stateId, ['state_write']);
    }

    public function testDeleteStateUsedByAnAddress(): void
    {
        $this->deleteItem('/states/' . self::STATE_USED_BY_AN_ADDRESS, ['state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(self::STATE_USED_BY_AN_ADDRESS, $this->getItem('/states/' . self::STATE_USED_BY_AN_ADDRESS, ['state_read'])['stateId']);
    }

    private function expectedState(int $stateId, array $overrides = []): array
    {
        return ['stateId' => $stateId] + $overrides + $this->getCreatePayload();
    }

    private function getCreatePayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Test State',
            'countryId' => 21,
            'zoneId' => 2,
            'isoCode' => 'XX',
            'enabled' => true,
        ];
    }
}
