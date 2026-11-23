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

class ReferenceCurrencyEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['currency_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get reference currency endpoint' => ['GET', '/currencies/references?isoCode=EUR'];
    }

    public function testGetReferenceCurrency(): void
    {
        // The localized values come from the CLDR data of each installed language, keyed by locale
        $this->assertEquals(
            [
                'isoCode' => 'EUR',
                'numericIsoCode' => '978',
                'names' => [
                    'en-US' => 'Euro',
                    'fr-FR' => 'euro',
                ],
                'symbols' => [
                    'en-US' => '€',
                    'fr-FR' => '€',
                ],
                'patterns' => [
                    'en-US' => '¤#,##0.00',
                    'fr-FR' => "#,##0.00\u{00A0}¤",
                ],
                'precision' => 2,
            ],
            $this->getItem('/currencies/references?isoCode=EUR', ['currency_read'])
        );
    }

    public function testGetUnknownReferenceCurrencyReturnsNotFound(): void
    {
        $this->requestApi(
            'GET',
            '/currencies/references?isoCode=ZZZ',
            null,
            ['currency_read'],
            Response::HTTP_NOT_FOUND
        );
    }
}
