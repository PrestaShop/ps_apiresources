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

        return $state['stateId'];
    }

    /**
     * @depends testAddState
     */
    public function testGetState(int $stateId): int
    {
        $expected = ['stateId' => $stateId] + $this->getCreatePayload();

        $state = $this->getItem('/states/' . $stateId, ['state_read']);
        $this->assertEquals($expected, $state);

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

        $expected = [
            'stateId' => $stateId,
            'name' => 'Updated Test State',
            'countryId' => 21,
            'zoneId' => 1,
            'isoCode' => 'XY',
            'enabled' => false,
        ];

        $updatedState = $this->partialUpdateItem('/states/' . $stateId, $patchData, ['state_write']);
        $this->assertEquals($expected, $updatedState);

        $fetched = $this->getItem('/states/' . $stateId, ['state_read']);
        $this->assertEquals($expected, $fetched);

        return $stateId;
    }

    /**
     * @depends testUpdateState
     */
    public function testDeleteState(int $stateId): void
    {
        // This endpoint returns an empty response and a 204 HTTP code.
        $return = $this->deleteItem('/states/' . $stateId, ['state_write']);
        $this->assertNull($return);

        // Fetching a state that does not exist returns a 404 (StateNotFoundException mapping).
        $this->getItem('/states/999999', ['state_read'], Response::HTTP_NOT_FOUND);
    }

    public function testInvalidState(): void
    {
        $invalidData = array_merge($this->getCreatePayload(), [
            // Name is required (NotBlank) on creation.
            'name' => '',
        ]);

        $validationErrorsResponse = $this->createItem(
            '/states',
            $invalidData,
            ['state_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'name',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);
    }

    private function getCreatePayload(): array
    {
        return [
            'name' => 'Test State',
            'countryId' => 21,
            'zoneId' => 2,
            'isoCode' => 'XX',
            'enabled' => true,
        ];
    }
}
