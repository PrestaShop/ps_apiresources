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

class CategoryBulkStatusEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['category_write']);
    }

    public static function tearDownAfterClass(): void
    {
        $ids = \Db::getInstance()->executeS(
            "SELECT `id_category` FROM `" . _DB_PREFIX_ . "category` WHERE `id_category` > 1 AND `id_category` IN (SELECT `id_category` FROM `" . _DB_PREFIX_ . "category_lang` WHERE `name` = 'Test category gap')"
        );
        foreach ($ids ?: [] as $row) {
            (new \Category((int) $row['id_category']))->delete();
        }

        parent::tearDownAfterClass();
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'bulk enable categories endpoint' => ['PUT', '/categories/bulk-enable'];
        yield 'bulk disable categories endpoint' => ['PUT', '/categories/bulk-disable'];
    }

    public function testBulkEnableCategories(): void
    {
        $firstId = $this->createCategory(false);
        $secondId = $this->createCategory(false);

        $this->updateItem(
            '/categories/bulk-enable',
            ['categoryIds' => [$firstId, $secondId]],
            ['category_write'],
            Response::HTTP_NO_CONTENT
        );

        foreach ([$firstId, $secondId] as $categoryId) {
            $this->assertTrue((bool) (new \Category($categoryId))->active);
        }
    }

    public function testBulkDisableCategories(): void
    {
        $firstId = $this->createCategory(true);
        $secondId = $this->createCategory(true);

        $this->updateItem(
            '/categories/bulk-disable',
            ['categoryIds' => [$firstId, $secondId]],
            ['category_write'],
            Response::HTTP_NO_CONTENT
        );

        foreach ([$firstId, $secondId] as $categoryId) {
            $this->assertFalse((bool) (new \Category($categoryId))->active);
        }
    }

    private static int $categoryCounter = 0;

    private function createCategory(bool $active): int
    {
        ++self::$categoryCounter;

        $category = new \Category();
        $category->id_parent = (int) \Configuration::get('PS_HOME_CATEGORY');
        $category->active = $active;

        foreach (\Language::getIDs(false) as $langId) {
            $category->name[(int) $langId] = 'Test category gap';
            $category->link_rewrite[(int) $langId] = 'test-category-gap-' . self::$categoryCounter . '-' . $langId;
        }

        $category->add();

        return (int) $category->id;
    }
}
