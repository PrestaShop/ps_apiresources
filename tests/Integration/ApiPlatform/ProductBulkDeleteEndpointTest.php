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
use Tests\Resources\Resetter\ProductResetter;

class ProductBulkDeleteEndpointTest extends ApiTestCase
{
    private static int $productCounter = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_write']);
    }

    public static function tearDownAfterClass(): void
    {
        ProductResetter::resetProducts();

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'bulk delete products endpoint' => ['DELETE', '/products/bulk-delete'];
    }

    public function testBulkDeleteProducts(): void
    {
        $firstProductId = $this->createProduct();
        $secondProductId = $this->createProduct();

        $this->bulkDeleteItems(
            '/products/bulk-delete',
            ['productIds' => [$firstProductId, $secondProductId]],
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        foreach ([$firstProductId, $secondProductId] as $productId) {
            $this->assertFalse(\Validate::isLoadedObject(new \Product($productId)));
        }
    }

    private function createProduct(): int
    {
        ++self::$productCounter;

        $product = new \Product();
        $product->price = 10.0;
        $product->id_category_default = (int) \Configuration::get('PS_HOME_CATEGORY');

        foreach (\Language::getIDs(false) as $langId) {
            $product->name[(int) $langId] = 'Test product gap';
            $product->link_rewrite[(int) $langId] = 'test-product-gap-' . self::$productCounter . '-' . $langId;
        }

        $product->add();
        $product->addToCategories([(int) \Configuration::get('PS_HOME_CATEGORY')]);

        return (int) $product->id;
    }
}
