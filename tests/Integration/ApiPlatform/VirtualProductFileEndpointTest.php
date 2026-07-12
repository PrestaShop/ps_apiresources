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

class VirtualProductFileEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'delete virtual product file endpoint' => ['DELETE', '/virtual-product-files/1'];
    }

    public function testDeleteVirtualProductFile(): void
    {
        $productId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` ORDER BY `id_product` ASC'
        );

        $download = new \ProductDownload();
        $download->id_product = $productId;
        $download->filename = sha1(uniqid('vpf', true));
        $download->display_filename = 'test-file';
        $download->active = true;
        $download->add();
        $virtualProductFileId = (int) $download->id;

        $this->requestApi(
            'DELETE',
            '/virtual-product-files/' . $virtualProductFileId,
            null,
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertFalse(\Validate::isLoadedObject(new \ProductDownload($virtualProductFileId)));
    }
}
