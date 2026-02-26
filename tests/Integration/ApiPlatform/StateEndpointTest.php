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

class StateEndpointTest extends ApiTestCase
{
    public static int $countryIdAU;

    public static int $countryIdFR;

    public static int $zoneIdEurope;

    public static int $zoneIdOceania;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['state_write']);

        self::$countryIdAU = \Db::getInstance()->getValue('SELECT id_country FROM `' . _DB_PREFIX_ . 'country` WHERE iso_code="AU"');
        self::$countryIdFR = \Db::getInstance()->getValue('SELECT id_country FROM `' . _DB_PREFIX_ . 'country` WHERE iso_code="FR"');
        self::$zoneIdEurope = \Db::getInstance()->getValue('SELECT id_zone FROM `' . _DB_PREFIX_ . 'zone` WHERE name="Europe"');
        self::$zoneIdOceania = \Db::getInstance()->getValue('SELECT id_zone FROM `' . _DB_PREFIX_ . 'zone` WHERE name="Oceania"');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['state']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/states',
        ];
        /*
                yield 'get endpoint' => [
                    'GET',
                    '/states/1',
                ];

                yield 'update endpoint' => [
                    'PATCH',
                    '/states/1',
                ];

                yield 'toggle status endpoint' => [
                    'PUT',
                    '/states/1/toggle-status',
                ];

                yield 'delete endpoint' => [
                    'DELETE',
                    '/states/1',
                ];

                yield 'list endpoint' => [
                    'GET',
                    '/states',
                ];

                yield 'bulk disable endpoint' => [
                    'PUT',
                    '/states/disable',
                ];

                yield 'bulk enable endpoint' => [
                    'PUT',
                    '/states/enable',
                ];
                */
    }

    public function testAddState(): int
    {
        $itemsCount = $this->countItems('/states', ['state_read']);

        $state = $this->createItem('/states', [
            'name' => 'Normandie',
            'isoCode' => 'FR-NOR',
            'countryId' => self::$countryIdFR,
            'zoneId' => self::$zoneIdEurope,
            'enabled' => false,
        ], ['state_write']);
        $this->assertArrayHasKey('stateId', $state);
        $stateId = $state['stateId'];
        $this->assertEquals(
            [
                'stateId' => $stateId,
            ],
            $state
        );

        $newItemsCount = $this->countItems('/states', ['state_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $stateId;
    }

    /**
     * @depends testAddState
     *
     * @param int $stateId
     *
     * @return int
     */
    public function testGetSupplier(int $stateId): int
    {
        $state = $this->getItem('/states/' . $stateId, ['state_read']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
            $state
        );

        return $stateId;
    }

    /**
     * @depends testGetSupplier
     *
     * @param int $stateId
     *
     * @return int
     */
    public function testPartialUpdateSupplier(int $stateId): int
    {
        // name
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'name' => 'My Supplier Updated',
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'address' => 'My address Updated',
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'address2' => 'My address 2 Updated',
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'postCode' => '67890',
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'city' => 'MyCityUpdated',
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'countryId' => self::$countryIdGB,
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'phone' => '0145123456',
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'mobilePhone' => '0656789012',
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'enabled' => true,
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'descriptions' => [
                'en-US' => 'Description EN Updated',
                'fr-FR' => 'Description FR Updated',
            ],
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'metaTitles' => [
                'en-US' => 'MetaTitle EN Updated',
                'fr-FR' => 'MetaTitle FR Updated',
            ],
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
        $updatedSupplier = $this->partialUpdateItem('/states/' . $stateId, [
            'metaDescriptions' => [
                'en-US' => 'MetaDescription EN Updated',
                'fr-FR' => 'MetaDescription FR Updated',
            ],
        ], ['state_write']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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

        return $stateId;
    }

    /**
     * @depends testPartialUpdateSupplier
     *
     * @param int $stateId
     *
     * @return int
     */
    public function testGetUpdatedSupplier(int $stateId): int
    {
        $state = $this->getItem('/states/' . $stateId, ['state_read']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
            $state
        );

        return $stateId;
    }

    /**
     * @depends testGetUpdatedSupplier
     *
     * @param int $stateId
     *
     * @return int
     */
    public function testToggleStatusSupplier(int $stateId): int
    {
        $this->updateItem('/states/' . $stateId . '/toggle-status', [], ['state_write'], Response::HTTP_NO_CONTENT);
        $state = $this->getItem('/states/' . $stateId, ['state_read']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
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
            $state
        );

        return $stateId;
    }

    /**
     * @depends testToggleStatusSupplier
     *
     * @param int $stateId
     *
     * @return int
     */
    public function testListSuppliers(int $stateId): int
    {
        $states = $this->listItems('/states', ['state_read']);
        $this->assertGreaterThanOrEqual(2, $states['totalItems']);

        // Search for the one created previously during the tests and assert its data in the list
        $testSupplier = null;
        foreach ($states['items'] as $state) {
            if ($state['stateId'] === $stateId) {
                $testSupplier = $state;
            }
        }
        $this->assertNotNull($testSupplier);
        $this->assertEquals(
            [
                'stateId' => $stateId,
                'name' => 'My Supplier Updated',
                'enabled' => false,
                'productsCount' => 0,
            ],
            $testSupplier
        );

        return $stateId;
    }

    /**
     * @depends testListSuppliers
     *
     * @param int $stateId
     *
     * @return int
     */
    public function testDeleteSupplierLogo(int $stateId): int
    {
        $return = $this->deleteItem('/states/' . $stateId . '/logo', ['state_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        $state = $this->getItem('/states/' . $stateId, ['state_read']);

        return $stateId;
    }

    /**
     * @depends testDeleteSupplierLogo
     *
     * @param int $stateId
     *
     * @return void
     */
    public function testDeleteSupplier(int $stateId): void
    {
        $return = $this->deleteItem('/states/' . $stateId, ['state_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/states/' . $stateId, ['state_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteSupplier
     *
     * @param int $stateId
     *
     * @return array<int>
     */
    public function testBulkEnable(): array
    {
        $states = $this->listItems('/states', ['state_read']);
        $this->assertGreaterThanOrEqual(2, $states['totalItems']);

        $state1 = $this->createItem('/states', [
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
        ], ['state_write']);
        $this->assertArrayHasKey('stateId', $state1);
        $stateId1 = $state1['stateId'];

        $state2 = $this->createItem('/states', [
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
        ], ['state_write']);
        $this->assertArrayHasKey('stateId', $state2);
        $stateId2 = $state2['stateId'];

        $states = $this->listItems('/states', ['state_read']);
        $this->assertGreaterThanOrEqual(4, $states['totalItems']);

        $bulkSuppliers = [
            $stateId1,
            $stateId2,
        ];

        $this->updateItem('/states/enable', [
            'stateIds' => $bulkSuppliers,
        ], ['state_write'], Response::HTTP_NO_CONTENT);

        foreach ($bulkSuppliers as $stateId) {
            $state = $this->getItem('/states/' . $stateId, ['state_read']);
            $this->assertEquals(true, $state['enabled']);
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
        $this->updateItem('/states/disable', [
            'stateIds' => $bulkSuppliers,
        ], ['state_write'], Response::HTTP_NO_CONTENT);

        foreach ($bulkSuppliers as $stateId) {
            $state = $this->getItem('/states/' . $stateId, ['state_read']);
            $this->assertEquals(false, $state['enabled']);
        }

        return $bulkSuppliers;
    }

    /**
     * @depends testBulkDisable
     *
     * @param int $stateId
     *
     * @return void
     */
    public function testBulkDelete(array $bulkSuppliers): void
    {
        $this->updateItem('/states/delete', [
            'stateIds' => $bulkSuppliers,
        ], ['state_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided zones have been removed
        foreach ($bulkSuppliers as $stateId) {
            $this->getItem('/states/' . $stateId, ['state_read'], Response::HTTP_NOT_FOUND);
        }

        $this->assertEquals(2, $this->countItems('/states', ['state_read']));
    }

    public function testCreateInvalidState(): void
    {
        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/states', [
            'name' => '',
        ], ['state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'name',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'enabled',
                'message' => 'This value should not be null.',
            ],
        ], $validationErrorsResponse);
    }
}
