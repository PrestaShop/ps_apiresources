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

use PrestaShop\Module\APIResources\ApiPlatform\Resources\Carrier\Carrier;
use PrestaShop\PrestaShop\Core\Domain\Carrier\ValueObject\OutOfRangeBehavior;
use PrestaShop\PrestaShop\Core\Domain\Carrier\ValueObject\ShippingMethod;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class CarrierEndpointTest extends ApiTestCase
{
    /**
     * @var int[] the carriers this class uploaded a logo for, their logo files are cleaned up at the end
     */
    private static array $carrierIdsWithLogo = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::createApiClient(['carrier_read', 'carrier_write', 'tax_rules_group_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
        // The database is restored below, so the ids are reused by the next runs: the logo files must go as well, else
        // a carrier created later would be listed with a logo uploaded here
        foreach (self::$carrierIdsWithLogo as $carrierIdWithLogo) {
            self::removeCarrierLogoFiles($carrierIdWithLogo);
        }
        self::$carrierIdsWithLogo = [];
        DatabaseDump::restoreTables([
            'carrier',
            'carrier_group',
            'carrier_lang',
            'carrier_shop',
            'carrier_tax_rules_group_shop',
            'carrier_zone',
            'range_price',
            'range_weight',
            'delivery',
            'module_carrier',
            'tax_rules_group',
            'tax_rules_group_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/carriers'];
        yield 'get endpoint' => ['GET', '/carriers/1'];
        // The update is a POST so that a logo can be uploaded with it
        yield 'update endpoint' => ['POST', '/carriers/1'];
        yield 'get ranges endpoint' => ['GET', '/carriers/1/ranges'];
        yield 'set ranges endpoint' => ['PATCH', '/carriers/1/ranges'];
        yield 'set tax rule group endpoint' => ['PATCH', '/carriers/1/set-tax-rule-group'];
    }

    private function getCreatePayload(): array
    {
        return [
            'name' => 'My Carrier',
            'delays' => [
                'en-US' => '3-5 days',
                'fr-FR' => '3-5 jours',
            ],
            'grade' => 5,
            'trackingUrl' => 'http://example.com/@',
            'enabled' => true,
            'associatedGroupIds' => [1, 2, 3],
            'additionalHandlingFee' => false,
            'free' => false,
            'shippingMethod' => ShippingMethod::BY_PRICE,
            'rangeBehavior' => OutOfRangeBehavior::USE_HIGHEST_RANGE,
            'zones' => [1],
            'associatedShopIds' => [1],
            'maxWidth' => 0,
            'maxHeight' => 0,
            'maxDepth' => 0,
            'maxWeight' => 0,
        ];
    }

    public function testAddCarrier(): int
    {
        $carrier = $this->createItem('/carriers', $this->getCreatePayload(), ['carrier_write']);
        $this->assertArrayHasKey('carrierId', $carrier);
        $carrierId = $carrier['carrierId'];

        $this->assertEquals(
            [
                'carrierId' => $carrierId,
                'taxRuleGroupId' => 0,
                'position' => $carrier['position'],
                'ordersCount' => 0,
            ] + $this->getCreatePayload(),
            $carrier
        );

        return $carrierId;
    }

    /**
     * @depends testAddCarrier
     */
    public function testGetCarrier(int $carrierId): int
    {
        $carrier = $this->getItem('/carriers/' . $carrierId, ['carrier_read']);
        $this->assertEquals(
            [
                'carrierId' => $carrierId,
                'taxRuleGroupId' => 0,
                'position' => $carrier['position'],
                'ordersCount' => 0,
            ] + $this->getCreatePayload(),
            $carrier
        );

        return $carrierId;
    }

    /**
     * @depends testGetCarrier
     */
    public function testPartialUpdateCarrier(int $carrierId): int
    {
        // The update endpoint is a POST, and it only updates the fields of the payload
        $updatedCarrier = $this->createItem('/carriers/' . $carrierId, [
            'name' => 'My Carrier updated',
            'enabled' => false,
            'free' => true,
        ], ['carrier_write'], Response::HTTP_OK);

        $this->assertEquals('My Carrier updated', $updatedCarrier['name']);
        $this->assertFalse($updatedCarrier['enabled']);
        $this->assertTrue($updatedCarrier['free']);

        $fetchedCarrier = $this->getItem('/carriers/' . $carrierId, ['carrier_read']);
        $this->assertEquals($updatedCarrier, $fetchedCarrier);

        return $carrierId;
    }

    /**
     * @depends testPartialUpdateCarrier
     */
    public function testSetAndGetCarrierRanges(int $carrierId): int
    {
        $expectedRanges = [
            'carrierId' => $carrierId,
            'ranges' => [
                ['zoneId' => 1, 'rangeFrom' => 0.0, 'rangeTo' => 10.0, 'rangePrice' => 5.0],
                ['zoneId' => 1, 'rangeFrom' => 10.0, 'rangeTo' => 20.0, 'rangePrice' => 8.0],
            ],
        ];

        // The ranges are sent in the exact format the operations return, which is the point of
        // the shared format: a response can be copied as-is to build the next request
        $updatedRanges = $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/ranges',
            ['ranges' => $expectedRanges['ranges']],
            ['carrier_write']
        );

        $this->assertEquals($expectedRanges, $updatedRanges);
        $this->assertEquals($expectedRanges, $this->getItem('/carriers/' . $carrierId . '/ranges', ['carrier_read']));

        return $carrierId;
    }

    /**
     * @depends testSetAndGetCarrierRanges
     */
    public function testSetCarrierRangesWithOverlappingRangesIsRejected(int $carrierId): void
    {
        $this->partialUpdateItem('/carriers/' . $carrierId . '/ranges', [
            'ranges' => [
                ['zoneId' => 1, 'rangeFrom' => 0.0, 'rangeTo' => 10.0, 'rangePrice' => 5.0],
                ['zoneId' => 1, 'rangeFrom' => 5.0, 'rangeTo' => 15.0, 'rangePrice' => 8.0],
            ],
        ], ['carrier_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @depends testSetAndGetCarrierRanges
     */
    public function testSetCarrierTaxRuleGroup(int $carrierId): void
    {
        $taxRulesGroup = $this->createItem('/tax-rules-groups', [
            'name' => 'Carrier Tax Rules Group',
            'enabled' => true,
            'shopIds' => [1],
        ], ['tax_rules_group_write']);

        $updatedCarrier = $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/set-tax-rule-group',
            ['taxRuleGroupId' => $taxRulesGroup['taxRulesGroupId']],
            ['carrier_write']
        );

        // The operation returns the full carrier, not only the modified association. The expected
        // payload is the created one plus the fields changed by testPartialUpdateCarrier.
        $expectedCarrier = array_merge($this->getCreatePayload(), [
            'carrierId' => $carrierId,
            'name' => 'My Carrier updated',
            'enabled' => false,
            'free' => true,
            'taxRuleGroupId' => $taxRulesGroup['taxRulesGroupId'],
            'position' => $updatedCarrier['position'],
            'ordersCount' => 0,
        ]);

        $this->assertEquals($expectedCarrier, $updatedCarrier);
        $this->assertEquals($expectedCarrier, $this->getItem('/carriers/' . $carrierId, ['carrier_read']));
    }

    /**
     * @depends testSetAndGetCarrierRanges
     */
    public function testSetUnknownCarrierTaxRuleGroupIsRejected(int $carrierId): void
    {
        $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/set-tax-rule-group',
            ['taxRuleGroupId' => 999999],
            ['carrier_write'],
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * The create and update endpoints accept a multipart request, which is the only way to upload a logo since PHP
     * only fills the uploaded files of a POST request. The other tests of this class cover the JSON payloads.
     */
    public function testCreateCarrierWithLogo(): int
    {
        $createdCarrier = $this->requestApi('POST', '/carriers', null, ['carrier_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                // Form data values are all strings, and the nested ones use the bracket syntax
                'parameters' => [
                    'name' => 'Carrier created with a logo',
                    'delays' => [
                        'en-US' => '3-5 days',
                        'fr-FR' => '3-5 jours',
                    ],
                    'grade' => '1',
                    'trackingUrl' => 'http://example.com/track.php?num=@',
                    'enabled' => '1',
                    'associatedGroupIds' => ['1', '2', '3'],
                    'additionalHandlingFee' => '0',
                    'free' => '0',
                    'shippingMethod' => (string) ShippingMethod::BY_PRICE,
                    'rangeBehavior' => (string) OutOfRangeBehavior::USE_HIGHEST_RANGE,
                    'zones' => ['1'],
                    'associatedShopIds' => ['1'],
                ],
                'files' => [
                    'logo' => $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/Brown_bear_cushion.jpg'),
                ],
            ],
        ]);

        $carrierId = $createdCarrier['carrierId'];
        self::$carrierIdsWithLogo[] = $carrierId;

        $this->assertEquals('Carrier created with a logo', $createdCarrier['name']);
        $this->assertEquals('3-5 days', $createdCarrier['delays']['en-US']);
        // The logo is not part of the carrier payload, it is stored as the image of the carrier
        $this->assertFileExists(_PS_SHIP_IMG_DIR_ . $carrierId . '.jpg');
        $this->assertNotEmpty($this->getListedCarrierLogoUrl($carrierId));

        return $carrierId;
    }

    /**
     * @depends testCreateCarrierWithLogo
     */
    public function testUpdateCarrierLogo(int $carrierId): void
    {
        $previousLogoUrl = $this->getListedCarrierLogoUrl($carrierId);
        $this->assertNotEmpty($previousLogoUrl);

        // A logo can be replaced by a multipart update, which updates the other fields of the payload at the same time
        $updatedCarrier = $this->requestApi('POST', '/carriers/' . $carrierId, null, ['carrier_write'], Response::HTTP_OK, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'name' => 'Carrier with an updated logo',
                ],
                'files' => [
                    'logo' => $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/Hummingbird_cushion.jpg'),
                ],
            ],
        ]);

        self::$carrierIdsWithLogo[] = $updatedCarrier['carrierId'];

        $this->assertEquals('Carrier with an updated logo', $updatedCarrier['name']);
        $this->assertFileExists(_PS_SHIP_IMG_DIR_ . $updatedCarrier['carrierId'] . '.jpg');
        $this->assertNotEmpty($this->getListedCarrierLogoUrl($updatedCarrier['carrierId']));
    }

    /**
     * The logo is not exposed by the carrier itself, only the list exposes the url of the stored image.
     */
    private function getListedCarrierLogoUrl(int $carrierId): ?string
    {
        $carriers = $this->listItems('/carriers', ['carrier_read'], ['carrierId' => $carrierId]);
        $this->assertEquals(1, $carriers['totalItems']);

        return $carriers['items'][0]['logoUrl'];
    }

    /**
     * Removes the logo of a carrier, and the thumbnail the list generates out of it.
     */
    private static function removeCarrierLogoFiles(int $carrierId): void
    {
        $logoFiles = [
            _PS_SHIP_IMG_DIR_ . $carrierId . '.jpg',
            _PS_TMP_IMG_DIR_ . '/carrier_mini_' . $carrierId . '.jpg',
        ];

        foreach ($logoFiles as $logoFile) {
            if (file_exists($logoFile)) {
                unlink($logoFile);
            }
        }
    }

    /**
     * Payload limited to the fields the API cannot guess, so the ones documented as required: the fields defaulted by
     * the operation and the ones defaulted by the CQRS command (the sizes and the weight) are all omitted.
     */
    private function getMinimalistCreatePayload(): array
    {
        return [
            'name' => 'My Minimalist Carrier',
            'delays' => [
                'en-US' => '3-5 days',
                'fr-FR' => '3-5 jours',
            ],
            'grade' => 5,
            'trackingUrl' => 'http://example.com/@',
            'enabled' => true,
            'associatedGroupIds' => [1, 2, 3],
            'zones' => [1],
            'associatedShopIds' => [1],
        ];
    }

    private function assertCarrierMatchesMinimalistPayload(array $carrier, array $payload, array $expectedDefaultValues): void
    {
        // The payload comes first: a provided value always wins over a default one
        $this->assertEquals(
            $payload + [
                'carrierId' => $carrier['carrierId'],
                'position' => $carrier['position'],
                'taxRuleGroupId' => 0,
                'ordersCount' => 0,
                // Defaulted by the CQRS command itself, which has always been able to do it for its optional parameters
                'maxWidth' => 0,
                'maxHeight' => 0,
                'maxDepth' => 0,
                'maxWeight' => 0,
            ] + $expectedDefaultValues,
            $carrier
        );
    }

    /**
     * The fields with a default value can be omitted, exactly like the BO form fields that are left untouched.
     */
    public function testCreateCarrierWithTheDefaultValues(): void
    {
        // The default values are applied by the operation, a feature that only exists since PrestaShop 9.2.0
        $this->markTestSkippedByMinVersion('9.2.0');

        $payload = $this->getMinimalistCreatePayload();
        $carrier = $this->createItem('/carriers', $payload, ['carrier_write']);

        $this->assertCarrierMatchesMinimalistPayload($carrier, $payload, Carrier::CREATE_DEFAULT_VALUES);
        $this->assertFalse($carrier['free']);
    }

    /**
     * A value present in the payload is used as is, the default value of the operation only fills the absent ones.
     */
    public function testCreateFreeCarrierWithTheDefaultValues(): void
    {
        $this->markTestSkippedByMinVersion('9.2.0');

        $payload = array_merge($this->getMinimalistCreatePayload(), [
            'name' => 'My Free Minimalist Carrier',
            'free' => true,
        ]);
        $carrier = $this->createItem('/carriers', $payload, ['carrier_write']);

        // Every other field keeps its default value, only the free one is the provided value
        $this->assertCarrierMatchesMinimalistPayload($carrier, $payload, Carrier::CREATE_DEFAULT_VALUES);
        $this->assertTrue($carrier['free']);
    }

    public function testCreateInvalidCarrier(): void
    {
        $invalidPayload = array_merge($this->getCreatePayload(), [
            'name' => '',
            'zones' => [],
        ]);
        $validationErrorsResponse = $this->createItem(
            '/carriers',
            $invalidPayload,
            ['carrier_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            ['propertyPath' => 'name', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'name', 'message' => 'This value is too short. It should have 1 character or more.'],
            ['propertyPath' => 'zones', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'zones', 'message' => 'This collection should contain 1 element or more.'],
        ], $validationErrorsResponse);
    }

    /**
     * The endpoint enforces the rules of the BO form, so a payload that the form would reject is rejected here as
     * well, even when the CQRS command itself accepts it.
     */
    public function testCreateCarrierBreakingTheBackOfficeRules(): void
    {
        $invalidPayload = array_merge($this->getCreatePayload(), [
            'delays' => ['fr-FR' => '3-5 jours'],
            'grade' => 42,
            'trackingUrl' => 'not-an-url',
            'associatedGroupIds' => [],
            'associatedShopIds' => [],
            'maxWidth' => -1,
        ]);
        $validationErrorsResponse = $this->createItem(
            '/carriers',
            $invalidPayload,
            ['carrier_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            ['propertyPath' => 'delays', 'message' => 'The field delays is required at least in your default language.'],
            ['propertyPath' => 'grade', 'message' => 'This value should be between 0 and 9.'],
            ['propertyPath' => 'trackingUrl', 'message' => 'This value is not a valid URL.'],
            ['propertyPath' => 'maxWidth', 'message' => 'This value should be either positive or zero.'],
            ['propertyPath' => 'associatedGroupIds', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'associatedGroupIds', 'message' => 'This collection should contain 1 element or more.'],
            ['propertyPath' => 'associatedShopIds', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'associatedShopIds', 'message' => 'This collection should contain 1 element or more.'],
        ], $validationErrorsResponse);
    }

    /**
     * @depends testPartialUpdateCarrier
     */
    public function testUpdateCarrierWithoutGroupIsRejected(int $carrierId): void
    {
        // The BO form cannot save a carrier without group, so an update cannot empty the list either
        $validationErrorsResponse = $this->createItem(
            '/carriers/' . $carrierId,
            ['associatedGroupIds' => []],
            ['carrier_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            ['propertyPath' => 'associatedGroupIds', 'message' => 'This collection should contain 1 element or more.'],
        ], $validationErrorsResponse);
    }

    public function testCreateCarrierWithAnInvalidLogoIsRejected(): void
    {
        // The BO form only accepts a jpeg image as the logo, and so does this endpoint
        $validationErrorsResponse = $this->requestApi('POST', '/carriers', null, ['carrier_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'name' => 'Carrier with an invalid logo',
                    'delays' => ['en-US' => '3-5 days'],
                    'grade' => '1',
                    'trackingUrl' => 'http://example.com/@',
                    'enabled' => '1',
                    'associatedGroupIds' => ['1', '2', '3'],
                    'additionalHandlingFee' => '0',
                    'free' => '0',
                    'shippingMethod' => (string) ShippingMethod::BY_PRICE,
                    'rangeBehavior' => (string) OutOfRangeBehavior::USE_HIGHEST_RANGE,
                    'zones' => ['1'],
                    'associatedShopIds' => ['1'],
                ],
                'files' => [
                    'logo' => $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/archive/test_install_cqrs_command.zip'),
                ],
            ],
        ]);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            ['propertyPath' => 'logo', 'message' => 'Please upload a valid jpeg file'],
        ], $validationErrorsResponse);
    }
}
