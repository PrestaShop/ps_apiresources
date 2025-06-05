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

use PrestaShop\PrestaShop\Core\Domain\Discount\Command\AddDiscountCommand;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class DiscountEndpointTest extends ApiTestCase
{
    public const CART_LEVEL = 'cart_level';
    public const PRODUCT_LEVEL = 'product_level';
    public const FREE_GIFT = 'free_gift';
    public const FREE_SHIPPING = 'free_shipping';
    public const ORDER_LEVEL = 'order_level';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        LanguageResetter::resetLanguages();
        DatabaseDump::restoreTables([
            'cart_cart_rule',
            'cart_rule',
            'cart_rule_carrier',
            'cart_rule_combination',
            'cart_rule_country',
            'cart_rule_group',
            'cart_rule_lang',
            'cart_rule_product_rule',
            'cart_rule_product_rule_group',
            'cart_rule_product_rule_value',
            'cart_rule_shop',
        ]);

        self::addLanguageByLocale('fr-FR');
        // Check if the command exists, if it doesn't the scopes are not usable
        if (class_exists(AddDiscountCommand::class)) {
            self::createApiClient(['discount_write', 'discount_read']);
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        LanguageResetter::resetLanguages();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // If the discount domain does not exist we can skip all the tests here
        if (!class_exists(AddDiscountCommand::class)) {
            $this->markTestSkipped('AddDiscountCommand class does not exist');
        }
    }

    /**
     * @dataProvider discountTypesDataProvider
     *
     * @param string $type
     * @param array $names
     *
     * @return int
     */
    public function testAddDiscount(string $type, array $names, ?array $data): int
    {
        $bearerToken = $this->getBearerToken(['discount_write']);
        $json = [
            'type' => $type,
            'names' => $names,
        ];
        if ($data !== null) {
            $json = array_merge($json, $data);
        }
        $response = static::createClient()->request('POST', '/discount', [
            'auth_bearer' => $bearerToken,
            'json' => $json,
        ]);
        self::assertResponseStatusCodeSame(201);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        $this->assertArrayHasKey('discountId', $decodedResponse);
        $discountId = $decodedResponse['discountId'];
        $this->assertArrayHasKey(
            'type',
            $decodedResponse
        );
        $this->assertEquals($type, $decodedResponse['type']);

        return $discountId;
    }

    public function discountTypesDataProvider(): array
    {
        return [
            [
                self::CART_LEVEL,
                [
                    'en-US' => 'new cart level discount',
                    'fr-FR' => 'nouveau discount panier',
                ],
                null,
            ],
            [
                self::PRODUCT_LEVEL,
                [
                    'en-US' => 'new product level discount',
                    'fr-FR' => 'nouveau discount produit',
                ],
                [
                    'reductionProduct' => -1,
                    'percentDiscount' => 20.0,
                ],
            ],
            [
                self::FREE_GIFT,
                [
                    'en-US' => 'new free gift discount',
                    'fr-FR' => 'nouveau discount produit offert',
                ],
                [
                    'productId' => 1,
                ],
            ],
            [
                self::FREE_SHIPPING,
                [
                    'en-US' => 'new free shipping discount',
                    'fr-FR' => 'nouveau discount frais de port offert',
                ],
                null,
            ],
            [
                self::ORDER_LEVEL,
                [
                    'en-US' => 'new order level discount',
                    'fr-FR' => 'nouveau discount commande',
                ],
                null,
            ],
        ];
    }

    /**
     * @depends testAddDiscount
     *
     * @return int
     */
    public function testGetDiscount(): void
    {
        $bearerToken = $this->getBearerToken(['discount_read']);
        $response = static::createClient()->request('GET', '/discount/1', [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        $this->assertArrayHasKey('discountId', $decodedResponse);
        $this->assertEquals(1, $decodedResponse['priority']);
        $this->assertFalse($decodedResponse['active']);
        $this->assertArrayHasKey('validFrom', $decodedResponse);
        $this->assertArrayHasKey('validTo', $decodedResponse);
        $this->assertEquals(1, $decodedResponse['totalQuantity']);
        $this->assertEquals(1, $decodedResponse['quantityPerUser']);
        $this->assertEquals('', $decodedResponse['description']);
        $this->assertEquals('', $decodedResponse['code']);
        $this->assertEquals('0', $decodedResponse['customerId']);
        $this->assertFalse($decodedResponse['highlightInCart']);
        $this->assertTrue($decodedResponse['allowPartialUse']);
        $this->assertEquals(1, $decodedResponse['currencyId']);
        $this->assertEquals(0, $decodedResponse['reductionProduct']);
        $this->assertArrayHasKey(
            'type',
            $decodedResponse
        );
        $this->assertEquals('cart_level', $decodedResponse['type']);
    }

    public function testListDiscount(): void
    {
        $bearerToken = $this->getBearerToken(['discount_read']);
        $response = static::createClient()->request('GET', '/discounts', [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $decodedResponse = json_decode($response->getContent(), true);

        $this->assertCount(6, $decodedResponse);
    }

    public function testDeleteDiscount(): void
    {
        $bearerToken = $this->getBearerToken(['discount_write']);
        static::createClient()->request('DELETE', '/discount/1', [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(204);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/discount/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/discount',
        ];

        yield 'list endpoint' => [
            'GET',
            '/discounts',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/discount/1',
        ];
    }
}
