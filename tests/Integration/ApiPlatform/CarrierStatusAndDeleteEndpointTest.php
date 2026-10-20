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

class CarrierStatusAndDeleteEndpointTest extends ApiTestCase
{
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
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'delete endpoint' => ['DELETE', '/carriers/1'];
        yield 'bulk update status endpoint' => ['PUT', '/carriers/bulk-update-status'];
        yield 'bulk delete endpoint' => ['DELETE', '/carriers/bulk-delete'];
    }

    private function createCarrier(string $name): array
    {
        return $this->createItem('/carriers', [
            'name' => $name,
            'delays' => [
                'en-US' => '3-5 days',
                'fr-FR' => '3-5 jours',
            ],
            'grade' => 5,
            'trackingUrl' => 'http://example.com/@',
            'enabled' => true,
            'associatedGroupIds' => [1],
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
        ], ['carrier_write']);
    }

    public function testBulkUpdateStatus(): void
    {
        $first = $this->createCarrier('Bulk status carrier 1');
        $second = $this->createCarrier('Bulk status carrier 2');

        $this->updateItem('/carriers/bulk-update-status', [
            'carrierIds' => [$first['carrierId'], $second['carrierId']],
            'enabled' => false,
        ], ['carrier_write'], Response::HTTP_NO_CONTENT);

        foreach ([$first, $second] as $carrier) {
            $this->assertEquals(
                ['enabled' => false] + $carrier,
                $this->getItem('/carriers/' . $carrier['carrierId'], ['carrier_read'])
            );
        }
    }

    public function testInvalidBulkOperations(): void
    {
        $this->assertValidationErrors(
            [['propertyPath' => 'carrierIds', 'message' => 'This value should not be blank.']],
            $this->updateItem('/carriers/bulk-update-status', ['carrierIds' => [], 'enabled' => false], ['carrier_write'], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
        $this->assertValidationErrors(
            [['propertyPath' => 'carrierIds', 'message' => 'This value should not be blank.']],
            $this->bulkDeleteItems('/carriers/bulk-delete', ['carrierIds' => []], ['carrier_write'], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
        // Ids that are not positive are rejected by the carrier id value object
        $this->updateItem('/carriers/bulk-update-status', ['carrierIds' => [0], 'enabled' => false], ['carrier_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->bulkDeleteItems('/carriers/bulk-delete', ['carrierIds' => [0]], ['carrier_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDeleteCarrier(): void
    {
        $carrierId = $this->createCarrier('Deleted carrier')['carrierId'];

        $this->deleteItem('/carriers/' . $carrierId, ['carrier_write']);
        $this->getItem('/carriers/' . $carrierId, ['carrier_read'], Response::HTTP_NOT_FOUND);
    }

    public function testDeleteUnknownCarrierIsNotFound(): void
    {
        $this->deleteItem('/carriers/999999', ['carrier_write'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteCarriers(): void
    {
        $first = $this->createCarrier('Bulk deleted carrier 1')['carrierId'];
        $second = $this->createCarrier('Bulk deleted carrier 2')['carrierId'];

        $this->bulkDeleteItems('/carriers/bulk-delete', [
            'carrierIds' => [$first, $second],
        ], ['carrier_write'], Response::HTTP_NO_CONTENT);

        foreach ([$first, $second] as $carrierId) {
            $this->getItem('/carriers/' . $carrierId, ['carrier_read'], Response::HTTP_NOT_FOUND);
        }
    }
}
