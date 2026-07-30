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
    public static function getProtectedEndpoints(): iterable
    {
        yield 'list attribute groups with attributes' => ['GET', '/attributes/groups-with-attributes'];
    }

    public function testListAttributeGroupsWithAttributes(): void
    {
        // For CQRSGetCollection, getItem() returns the raw decoded JSON — which is a
        // list of rows for a collection endpoint, not a single-item object.
        $result = $this->getItem('/attributes/groups-with-attributes', ['attribute_group_read']);

        // Structural assertions: the demo fixture set (attribute ids, positions, colors,
        // translations) drifts between PrestaShop versions, so we validate the aggregate
        // shape and per-group invariants rather than snapshotting exact values.
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result), 'Expected at least one attribute group in the default fixtures.');

        $groupKeys = ['attributeGroupId', 'names', 'publicNames', 'type', 'colorGroup', 'position', 'attributes'];
        $attributeKeys = ['attributeId', 'position', 'color', 'localizedNames', 'textureFilePath'];
        $validTypes = ['select', 'radio', 'color'];

        foreach ($result as $group) {
            foreach ($groupKeys as $key) {
                $this->assertArrayHasKey($key, $group, sprintf('Group is missing "%s".', $key));
            }
            $this->assertIsInt($group['attributeGroupId']);
            $this->assertIsArray($group['names']);
            $this->assertNotEmpty($group['names']);
            $this->assertIsArray($group['publicNames']);
            $this->assertNotEmpty($group['publicNames']);
            $this->assertContains($group['type'], $validTypes, sprintf('Unexpected type "%s".', (string) $group['type']));
            $this->assertIsBool($group['colorGroup']);
            $this->assertIsInt($group['position']);
            $this->assertIsArray($group['attributes']);

            // Color groups must have colorGroup=true; conversely, colorGroup=true only for type "color".
            $this->assertSame($group['type'] === 'color', $group['colorGroup']);

            foreach ($group['attributes'] as $attribute) {
                foreach ($attributeKeys as $key) {
                    $this->assertArrayHasKey($key, $attribute, sprintf('Attribute is missing "%s".', $key));
                }
                $this->assertIsInt($attribute['attributeId']);
                $this->assertIsInt($attribute['position']);
                $this->assertIsString($attribute['color']);
                $this->assertIsArray($attribute['localizedNames']);
                $this->assertNotEmpty($attribute['localizedNames']);
                // textureFilePath is nullable when no texture is attached.
                if ($attribute['textureFilePath'] !== null) {
                    $this->assertIsString($attribute['textureFilePath']);
                }
            }
        }

        // Sanity-check the default fixtures: the "Color" group (id 2) must exist, be flagged
        // as a color group, and carry a non-empty attribute list.
        $groupsById = [];
        foreach ($result as $group) {
            $groupsById[$group['attributeGroupId']] = $group;
        }
        $this->assertArrayHasKey(2, $groupsById, 'Default fixtures should include the "Color" attribute group (id 2).');
        $this->assertTrue($groupsById[2]['colorGroup']);
        $this->assertSame('color', $groupsById[2]['type']);
        $this->assertNotEmpty($groupsById[2]['attributes']);
    }
}
