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

use Tests\Resources\Resetter\LanguageResetter;
use Tests\Resources\Resetter\ProductResetter;
use Tests\Resources\ResourceResetter;

class ProductListingEndpointTest extends ApiTestCase
{
    protected static int $frenchLangId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        (new ResourceResetter())->backupTestModules();
        ProductResetter::resetProducts();
        LanguageResetter::resetLanguages();
        self::$frenchLangId = self::addLanguageByLocale('fr-FR');
        self::createApiClient(['product_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        LanguageResetter::resetLanguages();
        // Reset modules folder that are removed with the FR language
        (new ResourceResetter())->resetTestModules();
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'list endpoint' => [
            'GET',
            '/products',
        ];
    }

    public function testListProducts(): void
    {
        $bearerToken = $this->getBearerToken([
            'product_read',
        ]);

        $response = static::createClient()->request('GET', '/products', ['auth_bearer' => $bearerToken]);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(19, json_decode($response->getContent())->items);

        $response = static::createClient()->request('GET', '/products?limit=10', ['auth_bearer' => $bearerToken]);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(10, json_decode($response->getContent())->items);

        $response = static::createClient()->request('GET', '/products?limit=1&orderBy=id_product&sortOrder=desc', ['auth_bearer' => $bearerToken]);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, json_decode($response->getContent())->items);
        $returnedProduct = json_decode($response->getContent());
        self::assertEquals('id_product', $returnedProduct->orderBy);
        self::assertEquals('desc', $returnedProduct->sortOrder);
        self::assertEquals(1, $returnedProduct->limit);
        self::assertEquals([], $returnedProduct->filters);
        self::assertEquals('Customizable mug', $returnedProduct->items[0]->name);
        self::assertEquals(300, $returnedProduct->items[0]->quantity);
        self::assertEquals('13.900000', $returnedProduct->items[0]->price);
        self::assertEquals('Home Accessories', $returnedProduct->items[0]->category);
        self::assertTrue($returnedProduct->items[0]->active);

        static::createClient()->request('GET', '/products');
        self::assertResponseStatusCodeSame(401);
    }
}
