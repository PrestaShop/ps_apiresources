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

class LanguageEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['language_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get language endpoint' => ['GET', '/languages/1'];
    }

    public function testGetLanguage(): void
    {
        $result = $this->getItem('/languages/1', ['language_read']);

        $this->assertArrayHasKey('languageId', $result);
        $this->assertSame(1, $result['languageId']);
        $this->assertArrayHasKey('name', $result);
        $this->assertIsString($result['name']);
        $this->assertArrayHasKey('isoCode', $result);
        $this->assertIsString($result['isoCode']);
        $this->assertArrayHasKey('tagIETF', $result);
        $this->assertIsString($result['tagIETF']);
        $this->assertArrayHasKey('locale', $result);
        $this->assertIsString($result['locale']);
        $this->assertArrayHasKey('shortDateFormat', $result);
        $this->assertIsString($result['shortDateFormat']);
        $this->assertArrayHasKey('fullDateFormat', $result);
        $this->assertIsString($result['fullDateFormat']);
        $this->assertArrayHasKey('rtl', $result);
        $this->assertIsBool($result['rtl']);
        $this->assertArrayHasKey('enabled', $result);
        $this->assertIsBool($result['enabled']);
        $this->assertArrayHasKey('shopIds', $result);
        $this->assertIsArray($result['shopIds']);
    }

    public function testGetUnknownLanguageReturnsNotFound(): void
    {
        $this->requestApi(
            'GET',
            '/languages/999999',
            null,
            ['language_read'],
            Response::HTTP_NOT_FOUND
        );
    }
}
