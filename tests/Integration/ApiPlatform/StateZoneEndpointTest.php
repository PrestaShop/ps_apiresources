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

class StateZoneEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['state']);
        self::createApiClient(['state_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['state']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'bulk update zone endpoint' => [
            'PUT',
            '/states/bulk-update-zone',
        ];
    }

    public function testBulkUpdateStateZone(): void
    {
        // Move a couple of existing states (from the default fixtures) to another zone.
        $return = $this->updateItem(
            '/states/bulk-update-zone',
            [
                'stateIds' => [1, 2],
                'newZoneId' => 1,
            ],
            ['state_write'],
            Response::HTTP_NO_CONTENT
        );

        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);
    }
}
