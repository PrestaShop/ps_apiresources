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

class CategoryPositionEndpointTest extends ApiTestCase
{
    private static int $counter = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['category_write']);
    }

    public static function tearDownAfterClass(): void
    {
        $ids = \Db::getInstance()->executeS(
            'SELECT `id_category` FROM `' . _DB_PREFIX_ . 'category_lang` WHERE `name` = \'Test category position\''
        );
        foreach ($ids ?: [] as $row) {
            (new \Category((int) $row['id_category']))->delete();
        }

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update category position endpoint' => ['PUT', '/categories/1/positions'];
    }

    public function testUpdateCategoryPosition(): void
    {
        $parentCategoryId = (int) \Configuration::get('PS_HOME_CATEGORY');
        $firstCategoryId = $this->createCategory($parentCategoryId);
        $secondCategoryId = $this->createCategory($parentCategoryId);

        // Format: "<index>_<parentId>_<categoryId>". Sending the second category as the FIRST entry
        // (with index 0) tells the handler its target position is 0 — i.e. move it up.
        $positions = [
            '0_' . $parentCategoryId . '_' . $secondCategoryId,
            '1_' . $parentCategoryId . '_' . $firstCategoryId,
        ];

        $this->updateItem(
            '/categories/' . $secondCategoryId . '/positions',
            [
                'parentCategoryId' => $parentCategoryId,
                'way' => 0,
                'positions' => $positions,
                'foundFirst' => true,
            ],
            ['category_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertSame(
            0,
            (int) \Db::getInstance()->getValue(
                'SELECT `position` FROM `' . _DB_PREFIX_ . 'category` WHERE `id_category` = ' . $secondCategoryId
            )
        );
    }

    private function createCategory(int $parentCategoryId): int
    {
        ++self::$counter;

        $category = new \Category();
        $category->id_parent = $parentCategoryId;
        $category->active = true;

        foreach (\Language::getIDs(false) as $langId) {
            $category->name[(int) $langId] = 'Test category position';
            $category->link_rewrite[(int) $langId] = 'test-category-position-' . self::$counter . '-' . $langId;
        }

        $category->add();

        return (int) $category->id;
    }
}
