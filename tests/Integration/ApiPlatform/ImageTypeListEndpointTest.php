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

class ImageTypeListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['image_type_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list image types endpoint' => ['GET', '/image-types'];
    }

    public function testListImageTypes(): void
    {
        $paginatedImageTypes = $this->listItems('/image-types?orderBy=imageTypeId&sortOrder=asc&limit=2', ['image_type_read']);
        $this->assertSame('imageTypeId', $paginatedImageTypes['orderBy']);
        $this->assertCount(2, $paginatedImageTypes['items']);
        $this->assertSame([
            'imageTypeId' => 1,
            'name' => 'cart_default',
            'width' => 125,
            'height' => 125,
            'imageFitment' => self::isVersionAtLeast('9.2.0') ? 'fit' : null,
            'products' => true,
            'categories' => false,
            'manufacturers' => false,
            'suppliers' => false,
            'stores' => false,
        ], $paginatedImageTypes['items'][0]);
        $this->assertSame(2, $paginatedImageTypes['items'][1]['imageTypeId']);
    }

    public function testFilterImageTypes(): void
    {
        $listedNames = function (array $filters): array {
            $paginatedImageTypes = $this->listItems('/image-types?orderBy=imageTypeId&sortOrder=asc', ['image_type_read'], $filters);

            return array_column($paginatedImageTypes['items'], 'name');
        };

        $this->assertSame(['cart_default'], $listedNames(['imageTypeId' => 1]));
        $this->assertSame(['small_default'], $listedNames(['name' => 'small_def']));
        $this->assertContains('cart_default', $listedNames(['width' => 125]));
        $this->assertNotContains('small_default', $listedNames(['stores' => false, 'categories' => false]));
    }
}
