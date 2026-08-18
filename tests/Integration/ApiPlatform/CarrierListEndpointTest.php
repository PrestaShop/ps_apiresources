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

use PrestaShop\PrestaShop\Core\Domain\Carrier\ValueObject\OutOfRangeBehavior;
use PrestaShop\PrestaShop\Core\Domain\Carrier\ValueObject\ShippingMethod;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class CarrierListEndpointTest extends ApiTestCase
{
    /**
     * @var int[] the carriers created by this class, their logo files are cleaned up at the end
     */
    private static array $createdCarrierIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::createApiClient(['carrier_read', 'carrier_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
        // The database is restored below, so the ids are reused by the next runs: the logo files must go as well, else
        // a carrier created later would be listed with the logo uploaded here
        foreach (self::$createdCarrierIds as $createdCarrierId) {
            self::removeCarrierLogoFiles($createdCarrierId);
        }
        self::$createdCarrierIds = [];
        DatabaseDump::restoreTables([
            'carrier',
            'carrier_group',
            'carrier_lang',
            'carrier_shop',
            'carrier_zone',
            'module_carrier',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list endpoint' => ['GET', '/carriers'];
    }

    public function testListCarriers(): int
    {
        $carrierId = $this->createCarrier('Carrier for list');

        $carriers = $this->listItems('/carriers', ['carrier_read']);
        $this->assertGreaterThanOrEqual(1, $carriers['totalItems']);
        // The list is ordered by position, which is also the order the carriers are offered in
        $this->assertEquals('position', $carriers['orderBy']);

        $filtered = $this->listItems('/carriers', ['carrier_read'], ['name' => 'Carrier for list']);
        $this->assertEquals(1, $filtered['totalItems']);

        $listedCarrier = $filtered['items'][0];
        $this->assertIsInt($listedCarrier['position']);
        $this->assertEquals(
            [
                'carrierId' => $carrierId,
                'name' => 'Carrier for list',
                'delay' => '3-5 days',
                'enabled' => true,
                'free' => false,
                'position' => $listedCarrier['position'],
                // The carrier belongs to the shop itself and has no logo, the null values are listed as such
                'moduleName' => '',
                'logoUrl' => null,
            ],
            $listedCarrier
        );

        return $carrierId;
    }

    /**
     * @depends testListCarriers
     */
    public function testFilterCarriersByStatus(int $carrierId): void
    {
        $this->createItem('/carriers/' . $carrierId, ['enabled' => false], ['carrier_write'], Response::HTTP_OK);

        $disabled = $this->listItems('/carriers', ['carrier_read'], ['name' => 'Carrier for list', 'enabled' => false]);
        $this->assertEquals(1, $disabled['totalItems']);
        $this->assertFalse($disabled['items'][0]['enabled']);

        $enabled = $this->listItems('/carriers', ['carrier_read'], ['name' => 'Carrier for list', 'enabled' => true]);
        $this->assertEquals(0, $enabled['totalItems']);
    }

    public function testPositionsAreSyncedWhenACarrierIsMoved(): void
    {
        // Moving a carrier only shifts the other ones since PrestaShop 9.2.0, where the add/edit handlers
        // use the same position updater as the carriers list (PrestaShop/PrestaShop#42022): older cores
        // write the requested position without re-syncing the rest of the list
        $this->markTestSkippedByMinVersion('9.2.0');

        $firstCarrierId = $this->createCarrier('Positioned carrier 1');
        $secondCarrierId = $this->createCarrier('Positioned carrier 2');
        $thirdCarrierId = $this->createCarrier('Positioned carrier 3');

        // Carriers created without position are appended at the end of the list, in creation order
        $this->assertCarriersAreListedInThisOrder([$firstCarrierId, $secondCarrierId, $thirdCarrierId]);

        $firstPosition = $this->getListedPositions()[$firstCarrierId];
        $movedCarrier = $this->createItem(
            '/carriers/' . $thirdCarrierId,
            ['position' => $firstPosition],
            ['carrier_write'],
            Response::HTTP_OK
        );

        // The moved carrier takes the requested position, and the two others are shifted after it
        $this->assertEquals($firstPosition, $movedCarrier['position']);
        $this->assertCarriersAreListedInThisOrder([$thirdCarrierId, $firstCarrierId, $secondCarrierId]);

        $listedPositions = $this->getListedPositions();
        $this->assertSame(
            array_unique($listedPositions),
            $listedPositions,
            'Moving a carrier must not leave two carriers sharing the same position'
        );
    }

    private function createCarrier(string $name): int
    {
        $carrier = $this->createItem('/carriers', [
            'name' => $name,
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
        ], ['carrier_write']);

        $carrierId = $carrier['carrierId'];
        self::$createdCarrierIds[] = $carrierId;
        // A previous run may have left the logo of a carrier that had this id, which the list would expose
        self::removeCarrierLogoFiles($carrierId);

        return $carrierId;
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
     * @param int[] $expectedCarrierIds
     */
    private function assertCarriersAreListedInThisOrder(array $expectedCarrierIds): void
    {
        $listedCarrierIds = array_values(array_intersect(
            array_keys($this->getListedPositions()),
            $expectedCarrierIds
        ));

        $this->assertEquals($expectedCarrierIds, $listedCarrierIds);
    }

    /**
     * @return array<string, mixed> the carrier of that name, as the list exposes it
     */
    private function getListedCarrier(string $name): array
    {
        $carriers = $this->listItems('/carriers', ['carrier_read'], ['name' => $name]);
        $this->assertEquals(1, $carriers['totalItems']);

        return $carriers['items'][0];
    }

    /**
     * @return array<int, int> the listed positions, indexed by carrier id, in the order of the list
     */
    private function getListedPositions(): array
    {
        $carriers = $this->listItems('/carriers', ['carrier_read']);

        return array_column($carriers['items'], 'position', 'carrierId');
    }
}
