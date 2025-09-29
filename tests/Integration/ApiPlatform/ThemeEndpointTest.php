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

use Db;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class ThemeEndpointTest extends ApiTestCase
{
    protected static $dirTheme = _PS_ROOT_DIR_ . '/themes/%s';

    protected static $fileThemeRTL = _PS_ROOT_DIR_ . '/themes/%s/assets/css/theme_rtl.css';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['theme_write']);

        self::cleanThemes();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['shop']);

        self::cleanThemes();
    }

    public static function cleanThemes(): void
    {
        if (file_exists(sprintf(self::$fileThemeRTL, 'classic'))) {
            unlink(sprintf(self::$fileThemeRTL, 'classic'));
        }
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update endpoint (Adapt To RTL)' => [
            'PUT',
            '/themes/classic/adapt-to-rtl',
        ];
        yield 'update endpoint (Enable)' => [
            'PUT',
            '/themes/classic/enable',
        ];
        yield 'update endpoint (Reset)' => [
            'PUT',
            '/themes/classic/reset',
        ];
        yield 'delete endpoint' => [
            'DELETE',
            '/themes/classic',
        ];
    }

    public function testAdaptToRtl(): void
    {
        $themeName = 'classic';

        self::assertEquals(false, $this->isThemeAdaptedToRTL($themeName));
        $this->updateItem('/themes/' . $themeName . '/adapt-to-rtl', [], ['theme_write'], Response::HTTP_NO_CONTENT);
        self::assertEquals(true, $this->isThemeAdaptedToRTL($themeName));
    }

    /**
     * @depends testAdaptToRtl
     */
    public function testReset(): void
    {
        $themeName = 'classic';

        self::assertEquals('classic', $this->getCurrentTheme());
        $this->updateItem('/themes/' . $themeName . '/reset', [], ['theme_write']);
        self::assertEquals('classic', $this->getCurrentTheme());
    }

    /**
     * @depends testReset
     */
    public function testEnable(): void
    {
        $themeName = 'hummingbird';

        self::assertEquals('classic', $this->getCurrentTheme());
        $this->updateItem('/themes/' . $themeName . '/enable', [], ['theme_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @depends testEnable
     */
    public function testDelete(): void
    {
        $themeName = 'hummingbird';

        self::assertEquals(true, $this->hasTheme($themeName));
        $this->deleteItem('/themes/' . $themeName, ['theme_write']);
        self::assertEquals(false, $this->hasTheme($themeName));
    }

    /**
     * @depends testDelete
     */
    public function testImport(): void
    {
        $themeName = 'hummingbird';

        self::assertEquals(false, $this->hasTheme($themeName));
        $this->updateItem(
            '/themes/import',
            [
                'importSource' => [
                    'sourceType' => 'from_web',
                    'source' => 'https://github.com/PrestaShop/hummingbird/releases/download/v1.0.1/hummingbird.zip',
                ],
            ],
            ['theme_write'],
            Response::HTTP_NO_CONTENT
        );
        self::assertEquals(true, $this->hasTheme($themeName));
    }

    protected function hasTheme(string $themeName): bool
    {
        return is_dir(sprintf(self::$dirTheme, $themeName));
    }

    protected function isThemeAdaptedToRTL(string $themeName): bool
    {
        return file_exists(sprintf(self::$fileThemeRTL, $themeName));
    }

    protected function getCurrentTheme(): string
    {
        return \Db::getInstance()->getValue('SELECT theme_name FROM `' . _DB_PREFIX_ . 'shop` WHERE id_shop="1"');
    }
}
