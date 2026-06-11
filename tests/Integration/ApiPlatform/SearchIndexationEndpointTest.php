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

class SearchIndexationEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['search_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'search indexation endpoint' => [
            'PUT',
            '/searches/indexations',
        ];
    }

    public function testSearchIndexation(): void
    {
        $return = $this->updateItem(
            '/searches/indexations',
            ['full' => false],
            ['search_write'],
            Response::HTTP_NO_CONTENT
        );

        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);
    }
}
