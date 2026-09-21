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

use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class CartEndpointTest extends ApiTestCase
{
    // Fixture customer ID that always exists in the test DB
    private const FIXTURE_CUSTOMER_ID = 1;
    // Fixture product ID that always exists in the test DB
    private const FIXTURE_PRODUCT_ID = 1;
    // Second fixture product, a standard one without combination
    private const FIXTURE_SECOND_PRODUCT_ID = 6;
    // Fixture address ID that always exists in the test DB
    private const FIXTURE_ADDRESS_ID = 1;
    // Second address of the fixture customer
    private const FIXTURE_SECOND_ADDRESS_ID = 4;
    // Default currency ID (Euro) in the test DB
    private const FIXTURE_CURRENCY_ID = 1;
    // Default language ID in the test DB
    private const FIXTURE_LANGUAGE_ID = 1;
    // Name given to the cart rule fixtures created by this class
    private const CART_RULE_NAME = 'API test cart rule';

    public static function setUpBeforeClass(): void
    {
        if (self::isVersionUnder('9.2.0')) {
            static::markTestSkipped('The cart endpoints require PrestaShop >= 9.2.0, see Cart::VERSION_GATE');

            return;
        }

        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['cart_read', 'cart_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
        // CartRule::add() flips PS_CART_RULE_FEATURE_ACTIVE, so the configuration is restored for the next
        // test class. Only here: doing it in setUpBeforeClass would roll back the configuration
        // ApiTestCase::setUpBeforeClass() has just written for this class, PS_ADMIN_API_FORCE_DEBUG_SECURED
        // included, and every request of the class would then fail to authenticate.
        DatabaseDump::restoreTables(['configuration']);
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'cart',
            'cart_product',
            'cart_cart_rule',
            'cart_rule',
            'cart_rule_lang',
            'cart_rule_shop',
            'customization',
            'customized_data',
            // The price endpoint stores a specific price bound to the cart ID, and the restored cart table
            // hands the same IDs out again
            'specific_price',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get cart endpoint' => [
            'GET',
            '/carts/1',
        ];

        yield 'create cart endpoint' => [
            'POST',
            '/carts',
        ];

        yield 'delete cart endpoint' => [
            'DELETE',
            '/carts/1',
        ];

        yield 'get cart view endpoint' => [
            'GET',
            '/carts/1/view',
        ];

        yield 'add product to cart endpoint' => [
            'POST',
            '/carts/1/products',
        ];

        yield 'remove product from cart endpoint' => [
            'DELETE',
            '/carts/1/products/1',
        ];

        yield 'update product quantity endpoint' => [
            'PUT',
            '/carts/1/products/1/quantity',
        ];

        yield 'update product price endpoint' => [
            'PUT',
            '/carts/1/products/1/price',
        ];

        yield 'add cart rule endpoint' => [
            'POST',
            '/carts/1/cart-rules',
        ];

        yield 'remove cart rule endpoint' => [
            'DELETE',
            '/carts/1/cart-rules/1',
        ];

        yield 'update cart addresses endpoint' => [
            'PUT',
            '/carts/1/addresses',
        ];

        yield 'update cart carrier endpoint' => [
            'PUT',
            '/carts/1/carrier',
        ];

        yield 'update cart currency endpoint' => [
            'PUT',
            '/carts/1/currency',
        ];

        yield 'update cart delivery settings endpoint' => [
            'PUT',
            '/carts/1/delivery-settings',
        ];

        yield 'update cart language endpoint' => [
            'PUT',
            '/carts/1/language',
        ];

        yield 'bulk delete carts endpoint' => [
            'DELETE',
            '/carts/bulk-delete',
        ];
    }

    /**
     * The tests below are chained: each one receives the cart expected at that point, updates the part its
     * endpoint modifies and compares it with the whole response, so a side effect on any other field is caught.
     */
    public function testCreateCart(): array
    {
        $cart = $this->createItem('/carts', ['customerId' => self::FIXTURE_CUSTOMER_ID], ['cart_write']);

        $this->assertArrayHasKey('cartId', $cart);
        $cartId = $cart['cartId'];
        $this->assertIsInt($cartId);
        $this->assertGreaterThan(0, $cartId);

        $this->assertCount(2, $cart['addresses']);
        $expectedCart = [
            'cartId' => $cartId,
            'customerId' => self::FIXTURE_CUSTOMER_ID,
            'currencyId' => self::FIXTURE_CURRENCY_ID,
            'languageId' => self::FIXTURE_LANGUAGE_ID,
            'products' => [],
            'cartRules' => [],
            // The alias and the formatted address come from the fixtures, they are read once here and must
            // then stay identical in every following response
            'addresses' => [
                [
                    'addressId' => self::FIXTURE_ADDRESS_ID,
                    'alias' => $cart['addresses'][0]['alias'],
                    'formattedAddress' => $cart['addresses'][0]['formattedAddress'],
                    'delivery' => true,
                    'invoice' => true,
                ],
                [
                    'addressId' => self::FIXTURE_SECOND_ADDRESS_ID,
                    'alias' => $cart['addresses'][1]['alias'],
                    'formattedAddress' => $cart['addresses'][1]['formattedAddress'],
                    'delivery' => false,
                    'invoice' => false,
                ],
            ],
            // The core only fills the shipping block when the cart has a delivery option, which this fixture
            // cart never gets
            'shipping' => null,
            'summary' => [
                'totalProductsPrice' => '$0.00',
                'totalDiscount' => '$0.00',
                'totalShippingPrice' => '$0.00',
                'totalShippingWithoutTaxes' => '$0.00',
                'totalTaxes' => '$0.00',
                'totalPriceWithTaxes' => '$0.00',
                'totalPriceWithoutTaxes' => '$0.00',
                // Holds a token generated for the cart
                'processOrderLink' => $cart['summary']['processOrderLink'],
                'orderMessage' => '',
            ],
        ];
        $this->assertNotEmpty($expectedCart['summary']['processOrderLink']);
        $this->assertEquals($expectedCart, $cart);

        return $expectedCart;
    }

    /**
     * @depends testCreateCart
     */
    public function testGetCart(array $expectedCart): array
    {
        $cart = $this->getItem('/carts/' . $expectedCart['cartId'], ['cart_read']);

        $this->assertEquals($expectedCart, $cart);

        return $expectedCart;
    }

    /**
     * @depends testGetCart
     */
    public function testAddProductToCart(array $expectedCart): array
    {
        $response = $this->createItem('/carts/' . $expectedCart['cartId'] . '/products', [
            'productId' => self::FIXTURE_PRODUCT_ID,
            'quantity' => 2,
        ], ['cart_write'], Response::HTTP_CREATED);

        $this->assertCount(1, $response['products']);
        $expectedCart['products'] = [
            [
                'productId' => self::FIXTURE_PRODUCT_ID,
                'attributeId' => 0,
                'name' => 'Hummingbird printed t-shirt',
                'attribute' => '',
                'reference' => 'demo_1',
                'unitPrice' => '19.12',
                'quantity' => 2,
                'price' => '38.24',
                // The image link depends on the shop domain and the stock on the tests that ran before, they
                // are read once here and must then stay identical in every following response
                'imageLink' => $response['products'][0]['imageLink'],
                'customization' => null,
                'availableStock' => $response['products'][0]['availableStock'],
                'availableOutOfStock' => false,
                'gift' => false,
            ],
        ];

        // The CartProduct resource only returns the updated product list, not the whole cart
        $this->assertEquals($this->getExpectedCartProducts($expectedCart), $response);

        return $expectedCart;
    }

    /**
     * @depends testAddProductToCart
     */
    public function testUpdateProductQuantityInCart(array $expectedCart): array
    {
        $response = $this->updateItem(
            '/carts/' . $expectedCart['cartId'] . '/products/' . self::FIXTURE_PRODUCT_ID . '/quantity',
            ['quantity' => 5],
            ['cart_write']
        );

        $expectedCart['products'][0]['quantity'] = 5;
        $expectedCart['products'][0]['price'] = '95.6';
        $this->assertEquals($this->getExpectedCartProducts($expectedCart, self::FIXTURE_PRODUCT_ID), $response);

        return $expectedCart;
    }

    /**
     * @depends testUpdateProductQuantityInCart
     */
    public function testUpdateProductPriceInCart(array $expectedCart): array
    {
        $response = $this->updateItem(
            '/carts/' . $expectedCart['cartId'] . '/products/' . self::FIXTURE_PRODUCT_ID . '/price',
            // combinationId is required here, 0 stands for a product without combination
            ['combinationId' => 0, 'price' => 12.5],
            ['cart_write']
        );

        $expectedCart['products'][0]['unitPrice'] = '12.5';
        $expectedCart['products'][0]['price'] = '62.5';
        $this->assertEquals($this->getExpectedCartProducts($expectedCart, self::FIXTURE_PRODUCT_ID), $response);

        // The product endpoints do not return the summary, the whole cart is read to check it follows
        $expectedCart['summary']['totalProductsPrice'] = '$62.50';
        $expectedCart['summary']['totalPriceWithTaxes'] = '$62.50';
        $expectedCart['summary']['totalPriceWithoutTaxes'] = '$62.50';
        $this->assertEquals($expectedCart, $this->getItem('/carts/' . $expectedCart['cartId'], ['cart_read']));

        return $expectedCart;
    }

    /**
     * @depends testUpdateProductPriceInCart
     */
    public function testRemoveProductFromCart(array $expectedCart): array
    {
        // A second product proves the removal only targets the requested one instead of emptying the cart
        $response = $this->createItem('/carts/' . $expectedCart['cartId'] . '/products', [
            'productId' => self::FIXTURE_SECOND_PRODUCT_ID,
            'quantity' => 1,
        ], ['cart_write']);

        $this->assertCount(2, $response['products']);
        $expectedCart['products'][] = [
            'productId' => self::FIXTURE_SECOND_PRODUCT_ID,
            'attributeId' => 0,
            'name' => 'Mug The best is yet to come',
            'attribute' => '',
            'reference' => 'demo_11',
            'unitPrice' => '11.9',
            'quantity' => 1,
            'price' => '11.9',
            'imageLink' => $response['products'][1]['imageLink'],
            'customization' => null,
            'availableStock' => $response['products'][1]['availableStock'],
            'availableOutOfStock' => false,
            'gift' => false,
        ];
        $this->assertEquals($this->getExpectedCartProducts($expectedCart), $response);

        $response = $this->deleteItem(
            '/carts/' . $expectedCart['cartId'] . '/products/' . self::FIXTURE_PRODUCT_ID,
            ['cart_write'],
            Response::HTTP_OK
        );

        array_shift($expectedCart['products']);
        $this->assertEquals($this->getExpectedCartProducts($expectedCart, self::FIXTURE_PRODUCT_ID), $response);

        $response = $this->deleteItem(
            '/carts/' . $expectedCart['cartId'] . '/products/' . self::FIXTURE_SECOND_PRODUCT_ID,
            ['cart_write'],
            Response::HTTP_OK
        );

        $expectedCart['products'] = [];
        $this->assertEquals($this->getExpectedCartProducts($expectedCart, self::FIXTURE_SECOND_PRODUCT_ID), $response);

        $expectedCart['summary']['totalProductsPrice'] = '$0.00';
        $expectedCart['summary']['totalPriceWithTaxes'] = '$0.00';
        $expectedCart['summary']['totalPriceWithoutTaxes'] = '$0.00';

        return $expectedCart;
    }

    /**
     * @depends testRemoveProductFromCart
     */
    public function testUpdateCartAddresses(array $expectedCart): array
    {
        // The cart is created on the first address of the customer for both roles: only the delivery one is
        // moved, so the test fails if the command does nothing or mixes the two addresses up
        $cart = $this->updateItem('/carts/' . $expectedCart['cartId'] . '/addresses', [
            'deliveryAddressId' => self::FIXTURE_SECOND_ADDRESS_ID,
            'invoiceAddressId' => self::FIXTURE_ADDRESS_ID,
        ], ['cart_write']);

        $expectedCart['addresses'][0]['delivery'] = false;
        $expectedCart['addresses'][1]['delivery'] = true;
        $this->assertEquals($expectedCart, $cart);

        return $expectedCart;
    }

    /**
     * @depends testUpdateCartAddresses
     */
    public function testUpdateCartCurrency(array $expectedCart): array
    {
        // The fixture shop only installs one currency, so this can only assert the endpoint accepts the
        // cart current one. See testUpdateCartLanguage for a real change of value.
        $cart = $this->updateItem('/carts/' . $expectedCart['cartId'] . '/currency', [
            'currencyId' => self::FIXTURE_CURRENCY_ID,
        ], ['cart_write']);

        $this->assertEquals($expectedCart, $cart);

        return $expectedCart;
    }

    /**
     * @depends testUpdateCartCurrency
     */
    public function testUpdateCartLanguage(array $expectedCart): array
    {
        // Switching to the second language installed by ApiTestCase rather than to the one the cart already
        // has, otherwise the assertion would pass even if the command did nothing
        $secondLanguageId = (int) \Language::getIdByIso('fr');
        $this->assertNotSame(self::FIXTURE_LANGUAGE_ID, $secondLanguageId);

        $cart = $this->updateItem('/carts/' . $expectedCart['cartId'] . '/language', [
            'languageId' => $secondLanguageId,
        ], ['cart_write']);

        $expectedCart['languageId'] = $secondLanguageId;
        $this->assertEquals($expectedCart, $cart);

        $cart = $this->updateItem('/carts/' . $expectedCart['cartId'] . '/language', [
            'languageId' => self::FIXTURE_LANGUAGE_ID,
        ], ['cart_write']);

        $expectedCart['languageId'] = self::FIXTURE_LANGUAGE_ID;
        $this->assertEquals($expectedCart, $cart);

        return $expectedCart;
    }

    /**
     * @depends testUpdateCartLanguage
     */
    public function testUpdateCartDeliverySettings(array $expectedCart): array
    {
        $cartId = $expectedCart['cartId'];

        // The write structure mirrors the read one: the settings live in the shipping sub array
        $cart = $this->updateItem('/carts/' . $cartId . '/delivery-settings', [
            'shipping' => [
                'freeShipping' => false,
                'gift' => false,
                'recycledPackaging' => false,
                'giftMessage' => null,
            ],
        ], ['cart_write']);

        $this->assertEquals($expectedCart, $cart);

        // The response cannot confirm the write on its own: the core only fills the shipping block when the
        // cart has a delivery option, and returns null otherwise, as it does for this fixture cart. The
        // stored values are asserted directly instead, so the test still fails if the command stops working.
        $storedSettings = \Db::getInstance()->getRow(
            'SELECT `gift`, `recyclable` FROM `' . _DB_PREFIX_ . 'cart` WHERE `id_cart` = ' . (int) $cartId
        );
        $this->assertSame(0, (int) $storedSettings['gift']);
        $this->assertSame(0, (int) $storedSettings['recyclable']);

        $cart = $this->updateItem('/carts/' . $cartId . '/delivery-settings', [
            'shipping' => [
                'freeShipping' => false,
                'gift' => true,
                'recycledPackaging' => true,
                'giftMessage' => 'Happy birthday',
            ],
        ], ['cart_write']);

        $this->assertEquals($expectedCart, $cart);
        $storedSettings = \Db::getInstance()->getRow(
            'SELECT `gift`, `gift_message`, `recyclable` FROM `' . _DB_PREFIX_ . 'cart` WHERE `id_cart` = ' . (int) $cartId
        );
        $this->assertSame(1, (int) $storedSettings['gift']);
        $this->assertSame(1, (int) $storedSettings['recyclable']);
        $this->assertSame('Happy birthday', $storedSettings['gift_message']);

        // allowFreeShipping is the only required parameter of the command, and the only one that makes the
        // core add or remove a free shipping cart rule rather than write a column
        $cart = $this->updateItem('/carts/' . $cartId . '/delivery-settings', [
            'shipping' => [
                'freeShipping' => true,
                'gift' => false,
                'recycledPackaging' => false,
                'giftMessage' => null,
            ],
        ], ['cart_write']);

        $freeShippingRuleId = (int) \Db::getInstance()->getValue(
            'SELECT cr.`id_cart_rule` FROM `' . _DB_PREFIX_ . 'cart_cart_rule` ccr
             INNER JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.`id_cart_rule` = ccr.`id_cart_rule`
             WHERE ccr.`id_cart` = ' . (int) $cartId . ' AND cr.`free_shipping` = 1'
        );
        $this->assertGreaterThan(0, $freeShippingRuleId);

        // The applied rules are keyed by cart rule ID
        $expectedCart['cartRules'] = [
            $freeShippingRuleId => [
                'cartRuleId' => $freeShippingRuleId,
                'name' => 'Free Shipping',
                'description' => '',
                'value' => '0',
            ],
        ];
        $this->assertEquals($expectedCart, $cart);

        return $expectedCart;
    }

    /**
     * @depends testUpdateCartDeliverySettings
     */
    public function testDeleteCart(array $expectedCart): void
    {
        $result = $this->deleteItem('/carts/' . $expectedCart['cartId'], ['cart_write']);
        $this->assertNull($result);

        // Verify cart is gone
        $this->getItem('/carts/' . $expectedCart['cartId'], ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    public function testGetUnknownCartReturnsNotFound(): void
    {
        $this->getItem('/carts/' . $this->getUnknownCartId(), ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    public function testGetCartForViewing(): void
    {
        // Create a cart first
        $cart = $this->createItem('/carts', ['customerId' => self::FIXTURE_CUSTOMER_ID], ['cart_write']);
        $cartId = $cart['cartId'];

        // A product is needed to check that the product lines are mapped as well
        $this->createItem('/carts/' . $cartId . '/products', [
            'productId' => self::FIXTURE_PRODUCT_ID,
            'quantity' => 2,
        ], ['cart_write'], Response::HTTP_CREATED);

        $cartView = $this->getItem('/carts/' . $cartId . '/view', ['cart_read']);

        // The legacy query result containers must not leak into the contract, only their camelCase counterparts
        $this->assertSame([
            'cartId',
            'currencyId',
            'customer',
            'order',
            'products',
            'cartRules',
            'summary',
            'cartLink',
            'dateAdd',
            'dateUpd',
        ], array_keys($cartView));

        $this->assertSame($cartId, $cartView['cartId']);
        $this->assertSame(self::FIXTURE_CURRENCY_ID, $cartView['currencyId']);

        $this->assertSame([
            'customerId',
            'firstName',
            'lastName',
            'gender',
            'email',
            'registrationDate',
            'validOrdersCount',
            'totalSpentSinceRegistration',
        ], array_keys($cartView['customer']));
        $this->assertSame(self::FIXTURE_CUSTOMER_ID, $cartView['customer']['customerId']);

        $this->assertSame(['orderId', 'placedDate'], array_keys($cartView['order']));

        $this->assertCount(1, $cartView['products']);
        $this->assertSame([
            'productId',
            'name',
            'attribute',
            'reference',
            'supplierReference',
            'availableStock',
            'quantity',
            'unitPrice',
            'unitPriceFormatted',
            'totalPrice',
            'totalPriceFormatted',
            'image',
            'customization',
        ], array_keys($cartView['products'][0]));
        $this->assertSame(self::FIXTURE_PRODUCT_ID, $cartView['products'][0]['productId']);
        $this->assertSame(2, $cartView['products'][0]['quantity']);

        // An empty list must still be exposed, the mapping does not create the target path when the source is empty
        $this->assertSame([], $cartView['cartRules']);

        $this->assertSame([
            'totalProducts',
            'totalProductsFormatted',
            'totalDiscounts',
            'totalDiscountsFormatted',
            'totalWrapping',
            'totalWrappingFormatted',
            'totalShipping',
            'totalShippingFormatted',
            'total',
            'totalFormatted',
            'taxIncluded',
        ], array_keys($cartView['summary']));

        $this->assertIsString($cartView['cartLink']);
        $this->assertIsString($cartView['dateAdd']);
        $this->assertIsString($cartView['dateUpd']);

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testBulkDeleteCarts(): void
    {
        $cart1 = $this->createItem('/carts', ['customerId' => self::FIXTURE_CUSTOMER_ID], ['cart_write']);
        $cart1Id = $cart1['cartId'];

        // Add a product to cart1 so it is no longer empty, forcing a new cart to be created for cart2
        $this->createItem('/carts/' . $cart1Id . '/products', [
            'productId' => self::FIXTURE_PRODUCT_ID,
            'quantity' => 1,
        ], ['cart_write'], Response::HTTP_CREATED);

        $cart2 = $this->createItem('/carts', ['customerId' => self::FIXTURE_CUSTOMER_ID], ['cart_write']);
        $cart2Id = $cart2['cartId'];

        $this->assertNotEquals($cart1Id, $cart2Id);

        $this->bulkDeleteItems('/carts/bulk-delete', ['cartIds' => [$cart1Id, $cart2Id]], ['cart_write']);

        $this->getItem('/carts/' . $cart1Id, ['cart_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/carts/' . $cart2Id, ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    public function testAddAndRemoveCartRule(): void
    {
        $cartId = $this->createCartWithProduct();
        $cartRuleId = self::createCartRule();

        $cart = $this->createItem('/carts/' . $cartId . '/cart-rules', [
            'cartRuleId' => $cartRuleId,
        ], ['cart_write']);

        // The applied rules are keyed by cart rule ID
        $this->assertArrayHasKey($cartRuleId, $cart['cartRules']);
        $this->assertSame([
            'cartRuleId' => $cartRuleId,
            'name' => self::CART_RULE_NAME,
            'description' => $cart['cartRules'][$cartRuleId]['description'],
            'value' => $cart['cartRules'][$cartRuleId]['value'],
        ], $cart['cartRules'][$cartRuleId]);

        // Unlike a plain CQRSDelete, this one answers 200 with the updated cart
        $cart = $this->deleteItem(
            '/carts/' . $cartId . '/cart-rules/' . $cartRuleId,
            ['cart_write'],
            Response::HTTP_OK
        );

        $this->assertSame($cartId, $cart['cartId']);
        $this->assertSame([], $cart['cartRules']);

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testAddInvalidCartRuleToCart(): void
    {
        $cartId = $this->createCartWithProduct();
        // Expired yesterday, so the core rejects it as not applicable
        $cartRuleId = self::createCartRule(date('Y-m-d H:i:s', strtotime('-2 days')));

        $this->createItem(
            '/carts/' . $cartId . '/cart-rules',
            ['cartRuleId' => $cartRuleId],
            ['cart_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testUpdateCartCarrier(): void
    {
        $cartId = $this->createCartWithProduct();
        $carrierId = self::getActiveCarrierId();

        $cart = $this->updateItem('/carts/' . $cartId . '/carrier', [
            'carrierId' => $carrierId,
        ], ['cart_write']);

        $this->assertSame($cartId, $cart['cartId']);
        // selectedCarrierId is not asserted: it reflects the delivery option the core recomputes for
        // the cart, which does not have to be the carrier that was just set.
        $this->assertArrayHasKey('selectedCarrierId', $cart['shipping']);

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testUpdateCartCarrierWithUnknownCarrier(): void
    {
        $cartId = $this->createCartWithProduct();
        $unknownCarrierId = 1 + (int) \Db::getInstance()->getValue(
            'SELECT MAX(`id_carrier`) FROM `' . _DB_PREFIX_ . 'carrier`'
        );

        $this->updateItem(
            '/carts/' . $cartId . '/carrier',
            ['carrierId' => $unknownCarrierId],
            ['cart_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testAddUnknownProductToCartReturnsNotFound(): void
    {
        $cartId = $this->createCartWithProduct();
        $unknownProductId = 1 + (int) \Db::getInstance()->getValue(
            'SELECT MAX(`id_product`) FROM `' . _DB_PREFIX_ . 'product`'
        );

        $this->createItem('/carts/' . $cartId . '/products', [
            'productId' => $unknownProductId,
            'quantity' => 1,
        ], ['cart_write'], Response::HTTP_NOT_FOUND);

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testAddProductAboveAvailableStockReturnsUnprocessable(): void
    {
        $cartId = $this->createCartWithProduct();

        $this->createItem('/carts/' . $cartId . '/products', [
            'productId' => self::FIXTURE_PRODUCT_ID,
            'quantity' => 100000,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    // "0" matches the \d+ requirement of the URI, and the DTO constraints never apply to a value read
    // from the URI, so a zero identifier only gets rejected by the value objects of the core.
    public function testZeroProductIdInUriReturnsUnprocessable(): void
    {
        $cartId = $this->createCartWithProduct();

        $this->updateItem(
            '/carts/' . $cartId . '/products/0/quantity',
            ['quantity' => 1],
            ['cart_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testZeroCartIdOnCartViewReturnsUnprocessable(): void
    {
        $this->getItem('/carts/0/view', ['cart_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetUnknownCartViewReturnsNotFound(): void
    {
        $this->getItem('/carts/' . $this->getUnknownCartId() . '/view', ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteCartsWithoutIdsReturnsUnprocessable(): void
    {
        $this->bulkDeleteItems(
            '/carts/bulk-delete',
            ['cartIds' => []],
            ['cart_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function testRemoveCombinationLineFromCart(): void
    {
        $cartId = $this->createCartWithProduct();
        [$productId, $combinationId] = self::getProductWithCombination();

        $response = $this->createItem('/carts/' . $cartId . '/products', [
            'productId' => $productId,
            'quantity' => 1,
            'combinationId' => $combinationId,
        ], ['cart_write']);
        $combinationLines = array_filter(
            $response['products'],
            fn ($product) => $product['attributeId'] === $combinationId
        );
        $this->assertCount(1, $combinationLines);

        // Without a combinationId the command targets the line with no combination: nothing matches, yet the
        // core reports a successful removal, so the endpoint answers 200 and the cart is left untouched.
        $response = $this->requestApi(
            'DELETE',
            '/carts/' . $cartId . '/products/' . $productId,
            null,
            ['cart_write'],
            Response::HTTP_OK
        );
        $this->assertNotEmpty(array_filter(
            $response['products'],
            fn ($product) => $product['attributeId'] === $combinationId
        ));

        // The combination has to be named in the body for the line to be removed
        $response = $this->requestApi(
            'DELETE',
            '/carts/' . $cartId . '/products/' . $productId,
            ['combinationId' => $combinationId],
            ['cart_write'],
            Response::HTTP_OK
        );
        $this->assertEmpty(array_filter(
            $response['products'],
            fn ($product) => $product['attributeId'] === $combinationId
        ));

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testUpdateCartWithUnknownCurrencyReturnsNotFound(): void
    {
        $cartId = $this->createCartWithProduct();
        $unknownCurrencyId = 1 + (int) \Db::getInstance()->getValue(
            'SELECT MAX(`id_currency`) FROM `' . _DB_PREFIX_ . 'currency`'
        );

        $this->updateItem(
            '/carts/' . $cartId . '/currency',
            ['currencyId' => $unknownCurrencyId],
            ['cart_write'],
            Response::HTTP_NOT_FOUND
        );

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testUpdateCartWithUnknownLanguageReturnsNotFound(): void
    {
        $cartId = $this->createCartWithProduct();
        $unknownLanguageId = 1 + (int) \Db::getInstance()->getValue(
            'SELECT MAX(`id_lang`) FROM `' . _DB_PREFIX_ . 'lang`'
        );

        $this->updateItem(
            '/carts/' . $cartId . '/language',
            ['languageId' => $unknownLanguageId],
            ['cart_write'],
            Response::HTTP_NOT_FOUND
        );

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testBulkDeleteUnknownCartReportsNotFoundPerItem(): void
    {
        $cartId = $this->createCartWithProduct();
        $unknownCartId = $this->getUnknownCartId();

        // Each sub error carries its own status, resolved against this operation exceptionToStatus map
        $this->bulkCommandItemsWithExpectedErrors(
            'DELETE',
            '/carts/bulk-delete',
            ['cartIds' => [$cartId, $unknownCartId]],
            [
                [
                    'type' => CartNotFoundException::class,
                    'message' => sprintf('Cart #%d was not found', $unknownCartId),
                    'status' => Response::HTTP_NOT_FOUND,
                ],
            ],
            ['cart_write']
        );

        $this->getItem('/carts/' . $cartId, ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteCartsWithInvalidIdsReturnsValidationErrors(): void
    {
        $validationErrors = $this->bulkDeleteItems(
            '/carts/bulk-delete',
            ['cartIds' => [0, 'abc']],
            ['cart_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->assertValidationErrors([
            [
                'propertyPath' => 'cartIds[0]',
                'message' => 'This value should be positive.',
            ],
            [
                'propertyPath' => 'cartIds[1]',
                'message' => 'This value should be of type integer.',
            ],
        ], $validationErrors);
    }

    public function testCreateCartInvalidData(): void
    {
        $validationErrors = $this->createItem('/carts', [
            'customerId' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'customerId',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testAddProductToCartInvalidData(): void
    {
        $validationErrors = $this->createItem('/carts/1/products', [
            'productId' => -1,
            'quantity' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'productId',
                'message' => 'This value should be positive.',
            ],
            [
                'propertyPath' => 'quantity',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testUpdateProductQuantityInvalidData(): void
    {
        // productId comes from the URI, only the quantity can be invalid here
        $validationErrors = $this->updateItem('/carts/1/products/1/quantity', [
            'quantity' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'quantity',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testUpdateCartAddressesInvalidData(): void
    {
        $validationErrors = $this->updateItem('/carts/1/addresses', [
            'deliveryAddressId' => -1,
            'invoiceAddressId' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'deliveryAddressId',
                'message' => 'This value should be positive.',
            ],
            [
                'propertyPath' => 'invoiceAddressId',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testUpdateCartDeliverySettingsInvalidData(): void
    {
        $cartId = $this->createCartWithProduct();

        // A non boolean freeShipping must be a validation error, not a denormalization one
        $validationErrors = $this->updateItem('/carts/' . $cartId . '/delivery-settings', [
            'shipping' => ['freeShipping' => 'yes'],
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertValidationErrors([
            [
                'propertyPath' => 'shipping[freeShipping]',
                'message' => 'This value should be of type bool.',
            ],
        ], $validationErrors);

        // The three optional fields go through the same command, so they need the same treatment
        $validationErrors = $this->updateItem('/carts/' . $cartId . '/delivery-settings', [
            'shipping' => [
                'freeShipping' => false,
                'gift' => 'yes',
                'recycledPackaging' => 'no',
                'giftMessage' => ['not a string'],
            ],
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertValidationErrors([
            [
                'propertyPath' => 'shipping[gift]',
                'message' => 'This value should be of type bool.',
            ],
            [
                'propertyPath' => 'shipping[recycledPackaging]',
                'message' => 'This value should be of type bool.',
            ],
            [
                'propertyPath' => 'shipping[giftMessage]',
                'message' => 'This value should be of type string.',
            ],
        ], $validationErrors);

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testUpdateCartCarrierInvalidData(): void
    {
        $validationErrors = $this->updateItem('/carts/1/carrier', [
            'carrierId' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'carrierId',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testUpdateCartCurrencyInvalidData(): void
    {
        $validationErrors = $this->updateItem('/carts/1/currency', [
            'currencyId' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'currencyId',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testUpdateCartLanguageInvalidData(): void
    {
        $validationErrors = $this->updateItem('/carts/1/language', [
            'languageId' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'languageId',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    /**
     * Builds the response the CartProduct endpoints are expected to answer, which is limited to the product
     * list. The quantity, price and removal endpoints also echo the product ID of their URI.
     */
    private function getExpectedCartProducts(array $expectedCart, ?int $productId = null): array
    {
        $expectedResponse = [
            'cartId' => $expectedCart['cartId'],
            'products' => $expectedCart['products'],
        ];
        if (null !== $productId) {
            $expectedResponse['productId'] = $productId;
        }

        return $expectedResponse;
    }

    private function createCartWithProduct(): int
    {
        $cart = $this->createItem('/carts', ['customerId' => self::FIXTURE_CUSTOMER_ID], ['cart_write']);
        $cartId = $cart['cartId'];

        $this->createItem('/carts/' . $cartId . '/products', [
            'productId' => self::FIXTURE_PRODUCT_ID,
            'quantity' => 1,
        ], ['cart_write']);

        return $cartId;
    }

    /**
     * There is no endpoint creating a cart rule that is applicable out of the box, so the fixture is
     * built with the legacy object, like CartEmailEndpointTest does for its cart.
     */
    private static function createCartRule(?string $dateTo = null): int
    {
        $cartRule = new \CartRule();
        $cartRule->id_customer = 0;
        // Without a code the core auto-applies the rule to every applicable cart, and the endpoint
        // would then answer "This voucher is already in your cart"
        $cartRule->code = 'API_TEST_' . uniqid();
        $cartRule->date_from = date('Y-m-d H:i:s', strtotime('-1 day'));
        $cartRule->date_to = $dateTo ?? date('Y-m-d H:i:s', strtotime('+1 year'));
        $cartRule->quantity = 100;
        $cartRule->quantity_per_user = 100;
        $cartRule->reduction_percent = 10.0;
        $cartRule->active = true;
        foreach (\Language::getLanguages(false) as $language) {
            $cartRule->name[(int) $language['id_lang']] = self::CART_RULE_NAME;
        }
        $cartRule->add();

        return (int) $cartRule->id;
    }

    /**
     * @return array{0: int, 1: int} product id and one of its in stock combination ids
     */
    private static function getProductWithCombination(): array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT pa.`id_product`, pa.`id_product_attribute`
             FROM `' . _DB_PREFIX_ . 'product_attribute` pa
             INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = pa.`id_product`
             INNER JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                ON sa.`id_product` = pa.`id_product` AND sa.`id_product_attribute` = pa.`id_product_attribute`
             WHERE p.`active` = 1 AND sa.`quantity` > 0
             ORDER BY pa.`id_product`, pa.`id_product_attribute`'
        );

        return [(int) $row['id_product'], (int) $row['id_product_attribute']];
    }

    private static function getActiveCarrierId(): int
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT `id_carrier` FROM `' . _DB_PREFIX_ . 'carrier`
             WHERE `active` = 1 AND `deleted` = 0 ORDER BY `id_carrier` ASC'
        );
    }

    private function getUnknownCartId(): int
    {
        return 1 + (int) \Db::getInstance()->getValue(
            'SELECT MAX(`id_cart`) FROM `' . _DB_PREFIX_ . 'cart`'
        );
    }
}
