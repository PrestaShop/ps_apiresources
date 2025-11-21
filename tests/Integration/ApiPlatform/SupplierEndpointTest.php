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
use Tests\Resources\Resetter\LanguageResetter;

class SupplierEndpointTest extends ApiTestCase
{
    public static int $countryIdFR;

    public static int $countryIdGB;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Add the fr-FR language to test multi lang values accurately
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::createApiClient(['supplier_write', 'supplier_read']);

        self::$countryIdFR = \Db::getInstance()->getValue('SELECT id_country FROM `' . _DB_PREFIX_ . 'country` WHERE iso_code="FR"');
        self::$countryIdGB = \Db::getInstance()->getValue('SELECT id_country FROM `' . _DB_PREFIX_ . 'country` WHERE iso_code="GB"');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        LanguageResetter::resetLanguages();
        DatabaseDump::restoreTables(['address', 'supplier', 'supplier_lang', 'supplier_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/suppliers',
        ];

        yield 'get endpoint' => [
            'GET',
            '/suppliers/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/suppliers/1',
        ];

        yield 'toggle status endpoint' => [
            'PUT',
            '/suppliers/1/toggle-status',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/suppliers/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/suppliers',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/suppliers/bulk-delete',
        ];

        yield 'bulk disable endpoint' => [
            'PUT',
            '/suppliers/bulk-disable',
        ];

        yield 'bulk enable endpoint' => [
            'PUT',
            '/suppliers/bulk-enable',
        ];
    }

    public function testAddSupplier(): int
    {
        $itemsCount = $this->countItems('/suppliers', ['supplier_read']);

        $supplier = $this->createItem('/suppliers', [
            'name' => 'My Supplier',
            'address' => 'My address',
            'address2' => 'My address 2',
            'postCode' => '12345',
            'city' => 'MyCity',
            // 'stateId',
            'countryId' => self::$countryIdFR,
            'phone' => '0233123456',
            'mobilePhone' => '0678901234',
            // 'dni'
            'enabled' => false,
            'descriptions' => [
                'en-US' => 'Description EN',
                'fr-FR' => 'Description FR',
            ],
            'metaTitles' => [
                'en-US' => 'MetaTitle EN',
                'fr-FR' => 'MetaTitle FR',
            ],
            'metaDescriptions' => [
                'en-US' => 'MetaDescription EN',
                'fr-FR' => 'MetaDescription FR',
            ],
            'logoImage' => null,
            'shopIds' => [1],
        ], ['supplier_write']);
        $this->assertArrayHasKey('supplierId', $supplier);
        $supplierId = $supplier['supplierId'];
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
            ],
            $supplier
        );

        $newItemsCount = $this->countItems('/suppliers', ['supplier_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $supplierId;
    }

    /**
     * @depends testAddSupplier
     *
     * @param int $supplierId
     *
     * @return int
     */
    public function testGetSupplier(int $supplierId): int
    {
        $supplier = $this->getItem('/suppliers/' . $supplierId, ['supplier_read']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier',
                'address' => 'My address',
                'address2' => 'My address 2',
                'postCode' => '12345',
                'city' => 'MyCity',
                'stateId' => 0,
                'countryId' => self::$countryIdFR,
                'phone' => '0233123456',
                'mobilePhone' => '0678901234',
                'dni' => '',
                'enabled' => false,
                'descriptions' => [
                    'en-US' => 'Description EN',
                    'fr-FR' => 'Description FR',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN',
                    'fr-FR' => 'MetaTitle FR',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $supplier
        );

        return $supplierId;
    }

    /**
     * @depends testGetSupplier
     *
     * @param int $supplierId
     *
     * @return int
     */
    public function testPartialUpdateSupplier(int $supplierId): int
    {
        // name
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'name' => 'My Supplier Updated',
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address',
                'address2' => 'My address 2',
                'postCode' => '12345',
                'city' => 'MyCity',
                'stateId' => 0,
                'countryId' => self::$countryIdFR,
                'phone' => '0233123456',
                'mobilePhone' => '0678901234',
                'dni' => '',
                'enabled' => false,
                'descriptions' => [
                    'en-US' => 'Description EN',
                    'fr-FR' => 'Description FR',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN',
                    'fr-FR' => 'MetaTitle FR',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        // address
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'address' => 'My address Updated',
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2',
                'postCode' => '12345',
                'city' => 'MyCity',
                'stateId' => 0,
                'countryId' => self::$countryIdFR,
                'phone' => '0233123456',
                'mobilePhone' => '0678901234',
                'dni' => '',
                'enabled' => false,
                'descriptions' => [
                    'en-US' => 'Description EN',
                    'fr-FR' => 'Description FR',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN',
                    'fr-FR' => 'MetaTitle FR',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        // address2
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'address2' => 'My address 2 Updated',
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '12345',
                'city' => 'MyCity',
                'stateId' => 0,
                'countryId' => self::$countryIdFR,
                'phone' => '0233123456',
                'mobilePhone' => '0678901234',
                'dni' => '',
                'enabled' => false,
                'descriptions' => [
                    'en-US' => 'Description EN',
                    'fr-FR' => 'Description FR',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN',
                    'fr-FR' => 'MetaTitle FR',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        // postCode
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'postCode' => '67890',
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '67890',
                'city' => 'MyCity',
                'stateId' => 0,
                'countryId' => self::$countryIdFR,
                'phone' => '0233123456',
                'mobilePhone' => '0678901234',
                'dni' => '',
                'enabled' => false,
                'descriptions' => [
                    'en-US' => 'Description EN',
                    'fr-FR' => 'Description FR',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN',
                    'fr-FR' => 'MetaTitle FR',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        // city
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'city' => 'MyCityUpdated',
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '67890',
                'city' => 'MyCityUpdated',
                'stateId' => 0,
                'countryId' => self::$countryIdFR,
                'phone' => '0233123456',
                'mobilePhone' => '0678901234',
                'dni' => '',
                'enabled' => false,
                'descriptions' => [
                    'en-US' => 'Description EN',
                    'fr-FR' => 'Description FR',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN',
                    'fr-FR' => 'MetaTitle FR',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        // countryId
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'countryId' => self::$countryIdGB,
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '67890',
                'city' => 'MyCityUpdated',
                'stateId' => 0,
                'countryId' => self::$countryIdGB,
                'phone' => '0233123456',
                'mobilePhone' => '0678901234',
                'dni' => '',
                'enabled' => false,
                'descriptions' => [
                    'en-US' => 'Description EN',
                    'fr-FR' => 'Description FR',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN',
                    'fr-FR' => 'MetaTitle FR',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        // phone
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'phone' => '0145123456',
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '67890',
                'city' => 'MyCityUpdated',
                'stateId' => 0,
                'countryId' => self::$countryIdGB,
                'phone' => '0145123456',
                'mobilePhone' => '0678901234',
                'dni' => '',
                'enabled' => false,
                'descriptions' => [
                    'en-US' => 'Description EN',
                    'fr-FR' => 'Description FR',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN',
                    'fr-FR' => 'MetaTitle FR',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        // mobilePhone
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'mobilePhone' => '0656789012',
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '67890',
                'city' => 'MyCityUpdated',
                'stateId' => 0,
                'countryId' => self::$countryIdGB,
                'phone' => '0145123456',
                'mobilePhone' => '0656789012',
                'dni' => '',
                'enabled' => false,
                'descriptions' => [
                    'en-US' => 'Description EN',
                    'fr-FR' => 'Description FR',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN',
                    'fr-FR' => 'MetaTitle FR',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        // enabled
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'enabled' => true,
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '67890',
                'city' => 'MyCityUpdated',
                'stateId' => 0,
                'countryId' => self::$countryIdGB,
                'phone' => '0145123456',
                'mobilePhone' => '0656789012',
                'dni' => '',
                'enabled' => true,
                'descriptions' => [
                    'en-US' => 'Description EN',
                    'fr-FR' => 'Description FR',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN',
                    'fr-FR' => 'MetaTitle FR',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        // descriptions
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'descriptions' => [
                'en-US' => 'Description EN Updated',
                'fr-FR' => 'Description FR Updated',
            ],
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '67890',
                'city' => 'MyCityUpdated',
                'stateId' => 0,
                'countryId' => self::$countryIdGB,
                'phone' => '0145123456',
                'mobilePhone' => '0656789012',
                'dni' => '',
                'enabled' => true,
                'descriptions' => [
                    'en-US' => 'Description EN Updated',
                    'fr-FR' => 'Description FR Updated',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN',
                    'fr-FR' => 'MetaTitle FR',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        // metaTitles
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'metaTitles' => [
                'en-US' => 'MetaTitle EN Updated',
                'fr-FR' => 'MetaTitle FR Updated',
            ],
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '67890',
                'city' => 'MyCityUpdated',
                'stateId' => 0,
                'countryId' => self::$countryIdGB,
                'phone' => '0145123456',
                'mobilePhone' => '0656789012',
                'dni' => '',
                'enabled' => true,
                'descriptions' => [
                    'en-US' => 'Description EN Updated',
                    'fr-FR' => 'Description FR Updated',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN Updated',
                    'fr-FR' => 'MetaTitle FR Updated',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN',
                    'fr-FR' => 'MetaDescription FR',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        // metaDescriptions
        $updatedSupplier = $this->partialUpdateItem('/suppliers/' . $supplierId, [
            'metaDescriptions' => [
                'en-US' => 'MetaDescription EN Updated',
                'fr-FR' => 'MetaDescription FR Updated',
            ],
        ], ['supplier_write']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '67890',
                'city' => 'MyCityUpdated',
                'stateId' => 0,
                'countryId' => self::$countryIdGB,
                'phone' => '0145123456',
                'mobilePhone' => '0656789012',
                'dni' => '',
                'enabled' => true,
                'descriptions' => [
                    'en-US' => 'Description EN Updated',
                    'fr-FR' => 'Description FR Updated',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN Updated',
                    'fr-FR' => 'MetaTitle FR Updated',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN Updated',
                    'fr-FR' => 'MetaDescription FR Updated',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $updatedSupplier
        );

        return $supplierId;
    }

    /**
     * @depends testPartialUpdateSupplier
     *
     * @param int $supplierId
     *
     * @return int
     */
    public function testGetUpdatedSupplier(int $supplierId): int
    {
        $supplier = $this->getItem('/suppliers/' . $supplierId, ['supplier_read']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '67890',
                'city' => 'MyCityUpdated',
                'stateId' => 0,
                'countryId' => self::$countryIdGB,
                'phone' => '0145123456',
                'mobilePhone' => '0656789012',
                'dni' => '',
                'enabled' => true,
                'descriptions' => [
                    'en-US' => 'Description EN Updated',
                    'fr-FR' => 'Description FR Updated',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN Updated',
                    'fr-FR' => 'MetaTitle FR Updated',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN Updated',
                    'fr-FR' => 'MetaDescription FR Updated',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $supplier
        );

        return $supplierId;
    }

    /**
     * @depends testGetUpdatedSupplier
     *
     * @param int $supplierId
     *
     * @return int
     */
    public function testToggleStatusSupplier(int $supplierId): int
    {
        $this->updateItem('/suppliers/' . $supplierId . '/toggle-status', [], ['supplier_write'], Response::HTTP_NO_CONTENT);
        $supplier = $this->getItem('/suppliers/' . $supplierId, ['supplier_read']);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'address' => 'My address Updated',
                'address2' => 'My address 2 Updated',
                'postCode' => '67890',
                'city' => 'MyCityUpdated',
                'stateId' => 0,
                'countryId' => self::$countryIdGB,
                'phone' => '0145123456',
                'mobilePhone' => '0656789012',
                'dni' => '',
                'enabled' => false,
                'descriptions' => [
                    'en-US' => 'Description EN Updated',
                    'fr-FR' => 'Description FR Updated',
                ],
                'metaTitles' => [
                    'en-US' => 'MetaTitle EN Updated',
                    'fr-FR' => 'MetaTitle FR Updated',
                ],
                'metaDescriptions' => [
                    'en-US' => 'MetaDescription EN Updated',
                    'fr-FR' => 'MetaDescription FR Updated',
                ],
                'logoImage' => null,
                'shopIds' => [1],
            ],
            $supplier
        );

        return $supplierId;
    }

    /**
     * @depends testToggleStatusSupplier
     *
     * @param int $supplierId
     *
     * @return int
     */
    public function testListSuppliers(int $supplierId): int
    {
        $suppliers = $this->listItems('/suppliers', ['supplier_read']);
        $this->assertGreaterThanOrEqual(2, $suppliers['totalItems']);

        // Search for the one created previously during the tests and assert its data in the list
        $testSupplier = null;
        foreach ($suppliers['items'] as $supplier) {
            if ($supplier['supplierId'] === $supplierId) {
                $testSupplier = $supplier;
            }
        }
        $this->assertNotNull($testSupplier);
        $this->assertEquals(
            [
                'supplierId' => $supplierId,
                'name' => 'My Supplier Updated',
                'enabled' => false,
                'productsCount' => 0,
            ],
            $testSupplier
        );

        return $supplierId;
    }

    /**
     * @depends testListSuppliers
     *
     * @param int $supplierId
     *
     * @return int
     */
    public function testDeleteSupplierLogo(int $supplierId): int
    {
        $return = $this->deleteItem('/suppliers/' . $supplierId . '/logo', ['supplier_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        $supplier = $this->getItem('/suppliers/' . $supplierId, ['supplier_read']);

        return $supplierId;
    }

    /**
     * @depends testDeleteSupplierLogo
     *
     * @param int $supplierId
     *
     * @return void
     */
    public function testDeleteSupplier(int $supplierId): void
    {
        $return = $this->deleteItem('/suppliers/' . $supplierId, ['supplier_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/suppliers/' . $supplierId, ['supplier_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteSupplier
     *
     * @param int $supplierId
     *
     * @return array<int>
     */
    public function testBulkEnable(): array
    {
        $suppliers = $this->listItems('/suppliers', ['supplier_read']);
        $this->assertGreaterThanOrEqual(2, $suppliers['totalItems']);

        $supplier1 = $this->createItem('/suppliers', [
            'name' => 'My Supplier 1',
            'address' => 'My address',
            'address2' => 'My address 2',
            'postCode' => '12345',
            'city' => 'MyCity',
            // 'stateId',
            'countryId' => self::$countryIdFR,
            'phone' => '0233123456',
            'mobilePhone' => '0678901234',
            // 'dni'
            'enabled' => false,
            'descriptions' => [
                'en-US' => 'Description EN',
                'fr-FR' => 'Description FR',
            ],
            'metaTitles' => [
                'en-US' => 'MetaTitle EN',
                'fr-FR' => 'MetaTitle FR',
            ],
            'metaDescriptions' => [
                'en-US' => 'MetaDescription EN',
                'fr-FR' => 'MetaDescription FR',
            ],
            'logoImage' => null,
            'shopIds' => [1],
        ], ['supplier_write']);
        $this->assertArrayHasKey('supplierId', $supplier1);
        $supplierId1 = $supplier1['supplierId'];

        $supplier2 = $this->createItem('/suppliers', [
            'name' => 'My Supplier 2',
            'address' => 'My address',
            'address2' => 'My address 2',
            'postCode' => '12345',
            'city' => 'MyCity',
            // 'stateId',
            'countryId' => self::$countryIdFR,
            'phone' => '0233123456',
            'mobilePhone' => '0678901234',
            // 'dni'
            'enabled' => false,
            'descriptions' => [
                'en-US' => 'Description EN',
                'fr-FR' => 'Description FR',
            ],
            'metaTitles' => [
                'en-US' => 'MetaTitle EN',
                'fr-FR' => 'MetaTitle FR',
            ],
            'metaDescriptions' => [
                'en-US' => 'MetaDescription EN',
                'fr-FR' => 'MetaDescription FR',
            ],
            'logoImage' => null,
            'shopIds' => [1],
        ], ['supplier_write']);
        $this->assertArrayHasKey('supplierId', $supplier2);
        $supplierId2 = $supplier2['supplierId'];

        $suppliers = $this->listItems('/suppliers', ['supplier_read']);
        $this->assertGreaterThanOrEqual(4, $suppliers['totalItems']);

        $bulkSuppliers = [
            $supplierId1,
            $supplierId2,
        ];

        $this->updateItem('/suppliers/bulk-enable', [
            'supplierIds' => $bulkSuppliers,
        ], ['supplier_write'], Response::HTTP_NO_CONTENT);

        foreach ($bulkSuppliers as $supplierId) {
            $supplier = $this->getItem('/suppliers/' . $supplierId, ['supplier_read']);
            $this->assertEquals(true, $supplier['enabled']);
        }

        return $bulkSuppliers;
    }

    /**
     * @depends testBulkEnable
     *
     * @param array<int> $bulkSuppliers
     *
     * @return array<int>
     */
    public function testBulkDisable(array $bulkSuppliers): array
    {
        $this->updateItem('/suppliers/bulk-disable', [
            'supplierIds' => $bulkSuppliers,
        ], ['supplier_write'], Response::HTTP_NO_CONTENT);

        foreach ($bulkSuppliers as $supplierId) {
            $supplier = $this->getItem('/suppliers/' . $supplierId, ['supplier_read']);
            $this->assertEquals(false, $supplier['enabled']);
        }

        return $bulkSuppliers;
    }

    /**
     * @depends testBulkDisable
     *
     * @param int $supplierId
     *
     * @return void
     */
    public function testBulkDelete(array $bulkSuppliers): void
    {
        $this->bulkDeleteItems('/suppliers/bulk-delete', [
            'supplierIds' => $bulkSuppliers,
        ], ['supplier_write']);

        // Assert the provided zones have been removed
        foreach ($bulkSuppliers as $supplierId) {
            $this->getItem('/suppliers/' . $supplierId, ['supplier_read'], Response::HTTP_NOT_FOUND);
        }

        $this->assertEquals(2, $this->countItems('/suppliers', ['supplier_read']));
    }

    public function testCreateInvalidSupplier(): void
    {
        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/suppliers', [
            'name' => '',
        ], ['supplier_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'name',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'name',
                'message' => 'This value is too short. It should have 1 character or more.',
            ],
            [
                'propertyPath' => 'enabled',
                'message' => 'This value should not be null.',
            ],
        ], $validationErrorsResponse);
    }
}
