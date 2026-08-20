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

class EditLanguageEndpointTest extends ApiTestCase
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
        yield 'edit language endpoint' => ['PATCH', '/languages/1'];
    }

    public function testEditLanguage(): void
    {
        $languageId = 1;

        $this->requestApi(
            'PATCH',
            '/languages/' . $languageId,
            ['name' => 'RenamedTest'],
            ['language_write'],
            Response::HTTP_OK
        );

        $renamedName = (string) \Db::getInstance()->getValue(
            'SELECT `name` FROM `' . _DB_PREFIX_ . 'lang` WHERE `id_lang` = ' . $languageId
        );
        $this->assertSame('RenamedTest', $renamedName);
    }

    public function testEditUnknownLanguageReturnsNotFound(): void
    {
        $this->requestApi(
            'PATCH',
            '/languages/999999',
            ['name' => 'Whatever'],
            ['language_write'],
            Response::HTTP_NOT_FOUND
        );
    }
}
