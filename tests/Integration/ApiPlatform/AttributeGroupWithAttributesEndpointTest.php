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
            $this->assertArrayHasKey('type', $row);
            $this->assertIsString($row['type']);
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
                $this->assertArrayHasKey('localizedNames', $attribute);
                $this->assertIsArray($attribute['localizedNames']);
                $this->assertArrayHasKey('textureFilePath', $attribute);
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
        // setUpBeforeClass installs fr-FR alongside the default en-US, so both locale
        // keys must be present in the localized maps.
        $this->assertArrayHasKey('en-US', $colorGroup['names']);
        $this->assertArrayHasKey('fr-FR', $colorGroup['names']);
        $this->assertSame('Color', $colorGroup['names']['en-US']);
        $this->assertArrayHasKey('en-US', $colorGroup['publicNames']);
        $this->assertArrayHasKey('fr-FR', $colorGroup['publicNames']);
        $this->assertSame('color', $colorGroup['type']);
        $this->assertTrue($colorGroup['colorGroup']);
        $this->assertCount(14, $colorGroup['attributes'], 'Default fixtures ship 14 Color attributes.');

        // Every Color attribute has a non-empty color hex and at least one localized name.
        foreach ($colorGroup['attributes'] as $attribute) {
            $this->assertNotEmpty($attribute['color'], 'Attributes of a color group must expose a color hex.');
            $this->assertNotEmpty($attribute['localizedNames']);
        }
    }
}
