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
use Tests\Resources\Resetter\LanguageResetter;

class CountryEndpointTest extends ApiTestCase
{
    // France — present in all PS 9.x default fixtures
    private const FRANCE_ID = 8;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::createApiClient(['country_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => ['GET', '/countries/1'];
    }

    public function testGetCountry(): void
    {
        $country = $this->getItem('/countries/' . self::FRANCE_ID, ['country_read']);

        $this->assertEquals([
            'countryId' => self::FRANCE_ID,
            'names' => [
                'en-US' => 'France',
                'fr-FR' => 'France',
            ],
            'isoCode' => 'FR',
            'callPrefix' => 33,
            'defaultCurrencyId' => 0,
            'zoneId' => 1,
            'needZipCode' => true,
            'zipCodeFormat' => 'NNNNN',
            'addressFormat' => '',
            'enabled' => true,
            'containsStates' => false,
            'needIdNumber' => false,
            'displayTaxLabel' => true,
            'shopIds' => [1],
        ], $country);
    }

    public function testGetNonExistentCountry(): void
    {
        $this->getItem('/countries/999999', ['country_read'], Response::HTTP_NOT_FOUND);
    }
}
