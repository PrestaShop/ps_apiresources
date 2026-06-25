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

class DeleteImagesFromTypeEndpointTest extends ApiTestCase
{
    private static int $imageTypeId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['image_type_write']);

        self::$imageTypeId = (int) \Db::getInstance()->getValue(
            'SELECT `id_image_type` FROM `' . _DB_PREFIX_ . 'image_type` ORDER BY `id_image_type` ASC'
        );
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'delete images from type endpoint' => ['DELETE', '/image-types/1/images'];
    }

    public function testDeleteImagesFromType(): void
    {
        // Removes the generated thumbnail files of the given image type (none in a fresh install),
        // so a 204 confirms the command ran successfully.
        $return = $this->deleteItem('/image-types/' . self::$imageTypeId . '/images', ['image_type_write']);
        $this->assertNull($return);
    }
}
