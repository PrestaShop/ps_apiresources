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
        $this->assertNotEmpty($result);

        // Basic shape assertions on every returned row: guarantees the response
        // contract (field presence + type) rather than only key presence.
        foreach ($result as $row) {
            $this->assertArrayHasKey('attributeGroupId', $row);
            $this->assertIsInt($row['attributeGroupId']);
            $this->assertArrayHasKey('names', $row);
            $this->assertIsArray($row['names']);
            $this->assertNotEmpty($row['names']);
            $this->assertArrayHasKey('publicNames', $row);
            $this->assertIsArray($row['publicNames']);
            $this->assertArrayHasKey('groupType', $row);
            $this->assertIsString($row['groupType']);
            $this->assertArrayHasKey('colorGroup', $row);
            $this->assertIsBool($row['colorGroup']);
            $this->assertArrayHasKey('position', $row);
            $this->assertIsInt($row['position']);
            $this->assertArrayHasKey('attributes', $row);
            $this->assertIsArray($row['attributes']);

            foreach ($row['attributes'] as $attribute) {
                $this->assertArrayHasKey('attributeId', $attribute);
                $this->assertIsInt($attribute['attributeId']);
                $this->assertArrayHasKey('position', $attribute);
                $this->assertIsInt($attribute['position']);
                $this->assertArrayHasKey('color', $attribute);
                $this->assertArrayHasKey('name', $attribute);
                $this->assertIsString($attribute['name']);
                $this->assertArrayHasKey('imagePath', $attribute);
            }
        }

        // Assert against known default fixture data: the Color group (attributeGroupId = 2)
        // must be present and expose the expected aggregate shape.
        $colorGroup = null;
        foreach ($result as $row) {
            if ($row['attributeGroupId'] === 2) {
                $colorGroup = $row;
                break;
            }
        }
        $this->assertNotNull($colorGroup, 'Default Color attribute group (id=2) should be returned by the endpoint.');
        // Default English language id in fixtures is 1; also accept any locale that resolves to 'Color'.
        $this->assertContains('Color', $colorGroup['names']);
        $this->assertSame('color', $colorGroup['groupType']);
        $this->assertTrue($colorGroup['colorGroup']);
        $this->assertCount(14, $colorGroup['attributes'], 'Default fixtures ship 14 Color attributes.');

        // Every Color attribute has a non-null color hex.
        foreach ($colorGroup['attributes'] as $attribute) {
            $this->assertNotNull($attribute['color'], 'Attributes of a color group must expose a color hex.');
            $this->assertNotEmpty($attribute['name']);
        }
    }
}
