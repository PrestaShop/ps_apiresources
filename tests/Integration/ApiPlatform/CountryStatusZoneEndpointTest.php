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

class CountryStatusZoneEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['country', 'country_lang', 'country_shop']);
        self::createApiClient(['country_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['country', 'country_lang', 'country_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'toggle status endpoint' => ['PUT', '/countries/1/toggle-status'];
        yield 'bulk toggle status endpoint' => ['PUT', '/countries/bulk-toggle-status'];
        yield 'bulk update zone endpoint' => ['PUT', '/countries/bulk-update-zone'];
    }

    public function testToggleCountryStatus(): void
    {
        $countryId = (int) \Db::getInstance()->getValue(
            'SELECT `id_country` FROM `' . _DB_PREFIX_ . 'country` ORDER BY `id_country` ASC'
        );
        $before = (int) \Db::getInstance()->getValue(
            'SELECT `active` FROM `' . _DB_PREFIX_ . 'country` WHERE `id_country` = ' . $countryId
        );

        $this->requestApi(
            'PUT',
            '/countries/' . $countryId . '/toggle-status',
            null,
            ['country_write'],
            Response::HTTP_OK
        );

        $after = (int) \Db::getInstance()->getValue(
            'SELECT `active` FROM `' . _DB_PREFIX_ . 'country` WHERE `id_country` = ' . $countryId
        );
        $this->assertNotSame($before, $after);
    }

    public function testBulkToggleCountriesStatus(): void
    {
        $ids = array_map('intval', array_column(
            \Db::getInstance()->executeS(
                'SELECT `id_country` FROM `' . _DB_PREFIX_ . 'country` ORDER BY `id_country` ASC LIMIT 2'
            ),
            'id_country'
        ));

        $this->requestApi(
            'PUT',
            '/countries/bulk-toggle-status',
            ['enabled' => false, 'countryIds' => $ids],
            ['country_write'],
            Response::HTTP_OK
        );

        $activeCount = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'country` WHERE `active` = 1 AND `id_country` IN (' . implode(', ', $ids) . ')'
        );
        $this->assertSame(0, $activeCount);
    }

    public function testBulkUpdateCountriesZone(): void
    {
        $ids = array_map('intval', array_column(
            \Db::getInstance()->executeS(
                'SELECT `id_country` FROM `' . _DB_PREFIX_ . 'country` ORDER BY `id_country` ASC LIMIT 2'
            ),
            'id_country'
        ));
        $newZoneId = (int) \Db::getInstance()->getValue(
            'SELECT `id_zone` FROM `' . _DB_PREFIX_ . 'zone` WHERE `active` = 1 ORDER BY `id_zone` DESC LIMIT 1'
        );

        $this->requestApi(
            'PUT',
            '/countries/bulk-update-zone',
            ['countryIds' => $ids, 'newZoneId' => $newZoneId],
            ['country_write'],
            Response::HTTP_OK
        );

        $otherZoneCount = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'country` WHERE `id_zone` != ' . $newZoneId . ' AND `id_country` IN (' . implode(', ', $ids) . ')'
        );
        $this->assertSame(0, $otherZoneCount);
    }
}
