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

class ModuleHookEndpointTest extends ApiTestCase
{
    private const MIN_VERSION = '9.2.0';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // GetPossibleHooksForModule, HookModuleCommand and EditHookedModuleCommand were all
        // introduced in 9.2.0, so below that version ApiResourceScopesExtractor drops the three
        // operations and their routes do not exist.
        if (self::isVersionUnder(self::MIN_VERSION)) {
            static::markTestSkipped(sprintf('The module hook endpoints require PrestaShop >= %s.', self::MIN_VERSION));
        }

        self::createApiClient(['module_read', 'module_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'hook module endpoint' => ['POST', '/modules/1/hooks'];
        yield 'edit hooked module endpoint' => ['PATCH', '/modules/1/hooks/1'];
        yield 'possible hooks endpoint' => ['GET', '/modules/1/possible-hooks'];
    }

    public function testListPossibleHooksForModule(): void
    {
        $moduleId = (int) \Db::getInstance()->getValue(
            'SELECT `id_module` FROM `' . _DB_PREFIX_ . 'module` WHERE `active` = 1 ORDER BY `id_module` ASC'
        );

        $result = $this->getItem('/modules/' . $moduleId . '/possible-hooks', ['module_read']);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result, 'An active module should expose at least one hookable hook.');

        // HookableInfo names the hook id "id"; the resource renames it so that ApiPlatform does
        // not mistake it for the identifier of a URI that only carries {moduleId}
        foreach ($result as $hook) {
            $this->assertEquals(['hookId', 'name', 'title', 'registered'], array_keys($hook));
            $this->assertIsInt($hook['hookId']);
            $this->assertIsBool($hook['registered']);
        }
    }
}
