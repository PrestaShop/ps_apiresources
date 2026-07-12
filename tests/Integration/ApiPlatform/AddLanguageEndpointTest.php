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

class AddLanguageEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['lang', 'lang_shop']);
        self::createApiClient(['language_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['lang', 'lang_shop']);
        LanguageResetter::resetLanguages();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create language endpoint' => ['POST', '/languages'];
    }

    public function testCreateLanguage(): void
    {
        $body = [
            'name' => 'Testish',
            'isoCode' => 'ts',
            'tagIETF' => 'ts-TS',
            'shortDateFormat' => 'Y-m-d',
            'fullDateFormat' => 'Y-m-d H:i:s',
            'flagImagePath' => '',
            'noPictureImagePath' => '',
            'rtl' => false,
            'enabled' => true,
            'shopIds' => [1],
        ];

        $result = $this->requestApi(
            'POST',
            '/languages',
            $body,
            ['language_write'],
            Response::HTTP_CREATED
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('languageId', $result);
        $this->assertIsInt($result['languageId']);
        $this->assertGreaterThan(0, $result['languageId']);
        $this->assertSame('Testish', $result['name']);
        $this->assertSame('ts', $result['isoCode']);
        $this->assertSame(true, $result['enabled']);
    }
}
