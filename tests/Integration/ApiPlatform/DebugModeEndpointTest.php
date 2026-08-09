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

use PrestaShop\PrestaShop\Adapter\Debug\DebugMode;

class DebugModeEndpointTest extends ApiTestCase
{

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['debug_mode_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update endpoint' => [
            'PUT',
            '/debug/toggle',
        ];
    }

    public function testUpdateDebugMode(): void
    {
        /** @var DebugMode $debugMode */
        $debugMode = $this->getContainer()->get('prestashop.adapter.debug_mode');

        $currentDebugMode = $debugMode->getCurrentDebugMode();

        if ($currentDebugMode === "false") {
            $this->updateItem('/debug/toggle', [
                'enableDebugMode' => "true",
            ], ['debug_mode_write']);

            $this->assertEquals("true", $debugMode->getCurrentDebugMode());

            $this->updateItem('/debug/toggle', [
                'enableDebugMode' => "false",
            ], ['debug_mode_write']);

            $this->assertEquals("false", $debugMode->getCurrentDebugMode());
        } else {
            $this->updateItem('/debug/toggle', [
                'enableDebugMode' => "false",
            ], ['debug_mode_write']);

            $this->assertEquals("false", $debugMode->getCurrentDebugMode());

            $this->updateItem('/debug/toggle', [
                'enableDebugMode' => "true",
            ], ['debug_mode_write']);

            $this->assertEquals("true", $debugMode->getCurrentDebugMode());
        }



    }
}
