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

class AttributeGroupWithAttributesEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['attribute_group_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list attribute groups with attributes' => ['GET', '/attributes/groups-with-attributes'];
    }

    public function testListAttributeGroupsWithAttributes(): void
    {
        $result = $this->getItem('/attributes/groups-with-attributes', ['attribute_group_read']);

        $this->assertIsArray($result);
        foreach ($result as $row) {
            $this->assertArrayHasKey('attributeGroupId', $row);
            $this->assertIsInt($row['attributeGroupId']);
            $this->assertArrayHasKey('localizedNames', $row);
            $this->assertIsArray($row['localizedNames']);
            $this->assertArrayHasKey('groupType', $row);
            $this->assertArrayHasKey('colorGroup', $row);
            $this->assertArrayHasKey('attributes', $row);
            $this->assertIsArray($row['attributes']);
        }
    }
}
