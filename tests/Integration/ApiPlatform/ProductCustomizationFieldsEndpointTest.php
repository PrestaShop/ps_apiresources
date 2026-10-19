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

class ProductCustomizationFieldsEndpointTest extends ApiTestCase
{
    private static int $productId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_write']);

        self::$productId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` ORDER BY `id_product` ASC'
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['customization_field', 'customization_field_lang', 'product']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set customization fields endpoint' => ['PUT', '/products/1/customization-fields'];
        yield 'remove all customization fields endpoint' => ['DELETE', '/products/1/customization-fields'];
    }

    private function countFields(): int
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customization_field` WHERE `id_product` = ' . self::$productId
        );
    }

    public function testSetProductCustomizationFields(): int
    {
        $this->updateItem(
            '/products/' . self::$productId . '/customization-fields',
            ['customizationFields' => [
                [
                    'type' => 1,
                    'localized_names' => [1 => 'Engraving text'],
                    'is_required' => false,
                    'added_by_module' => false,
                ],
            ]],
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertGreaterThanOrEqual(1, $this->countFields());

        return self::$productId;
    }

    /**
     * @depends testSetProductCustomizationFields
     */
    public function testRemoveAllProductCustomizationFields(int $productId): void
    {
        $this->deleteItem('/products/' . $productId . '/customization-fields', ['product_write']);

        $this->assertSame(0, $this->countFields());
    }
}
