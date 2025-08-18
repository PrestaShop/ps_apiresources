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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class CategoryEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Add the fr-FR language to test multi lang values accurately
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        // Pre-create API client with needed scopes
        self::createApiClient(['category_read', 'category_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        LanguageResetter::resetLanguages();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'category',
            'category_lang',
            'category_group',
            'category_shop',
            'category_product',
        ]);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/category/3',
        ];

        yield 'create endpoint' => [
            'POST',
            '/category',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/category/10',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/category/10',
        ];
    }

    public function testAddCategory(): int
    {
        $postData = [
            'names' => [
                'en-US' => 'Category EN',
                'fr-FR' => 'Catégorie FR',
            ],
            'linkRewrites' => [
                'en-US' => 'category-en',
                'fr-FR' => 'categorie-fr',
            ],
            'isActive' => true,
            'parentCategoryId' => 2,
            'shopIds' => [1],
        ];

        $category = $this->createItem('/category', $postData, ['category_write']);
        $categoryId = $category['categoryId'];

        $this->assertArrayHasKey('categoryId', $category);

        $this->assertSame($postData['names'], $category['names']);
        $this->assertSame($postData['linkRewrites'], $category['linkRewrites']);

        return $categoryId;
    }

    /**
     * @depends testAddCategory
     */
    public function testGetCategory(int $categoryId): int
    {
        $category = $this->getItem('/category/' . $categoryId, ['category_read']);

        $this->assertSame(
            $category['names'],
            [
                'en-US' => 'Category EN',
                'fr-FR' => 'Catégorie FR',
            ]
        );

        $this->assertSame(
            $category['linkRewrites'],
            [
                'en-US' => 'category-en',
                'fr-FR' => 'categorie-fr',
            ]
        );

        return $categoryId;
    }

    /**
     * @depends testGetCategory
     */
    public function testListCategories(int $categoryId): int
    {
        $paginated = $this->listItems('/categories?orderBy=categoryId&sortOrder=desc', ['category_read']);

        $this->assertGreaterThanOrEqual(10, $paginated['totalItems']);

        // First item should be our test category
        $first = $paginated['items'][0];

        $this->assertArrayHasKey('categoryId', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('active', $first);
        $this->assertEquals($categoryId, $first['categoryId']);

        return $categoryId;
    }

    /**
     * @depends testListCategories
     */
    public function testDeleteCategory(int $categoryId): void
    {
        // Delete the item
        $this->requestApi(
            Request::METHOD_DELETE,
            '/category/' . $categoryId,
            ['mode' => 'associate_and_disable'],
            ['category_write']
        );

        // Fetching the item returns a 404 indicatjng it no longer exists
        $this->getItem('/category/' . $categoryId, ['category_read'], Response::HTTP_NOT_FOUND);
    }
}
