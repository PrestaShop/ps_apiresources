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

        // Assert the full result set (all default fixture groups, all fields) in one
        // shot: any new field on the group or attribute aggregate will surface here.
        $this->assertEquals($this->getExpectedDefaultFixtureGroups(), $result);
    }

    /**
     * Default demo fixture attribute groups, as returned by GetAttributeGroupList,
     * with fr-FR installed alongside en-US (see setUpBeforeClass).
     *
     * @return array<int, array<string, mixed>>
     */
    private function getExpectedDefaultFixtureGroups(): array
    {
        return [
            [
                'attributeGroupId' => 1,
                'names' => ['en-US' => 'Size', 'fr-FR' => 'Taille'],
                'publicNames' => ['en-US' => 'Size', 'fr-FR' => 'Taille'],
                'type' => 'select',
                'colorGroup' => false,
                'position' => 0,
                'attributes' => [
                    ['attributeId' => 1, 'position' => 0, 'color' => '', 'localizedNames' => ['en-US' => 'S', 'fr-FR' => 'S'], 'textureFilePath' => ''],
                    ['attributeId' => 2, 'position' => 1, 'color' => '', 'localizedNames' => ['en-US' => 'M', 'fr-FR' => 'M'], 'textureFilePath' => ''],
                    ['attributeId' => 3, 'position' => 2, 'color' => '', 'localizedNames' => ['en-US' => 'L', 'fr-FR' => 'L'], 'textureFilePath' => ''],
                    ['attributeId' => 4, 'position' => 3, 'color' => '', 'localizedNames' => ['en-US' => 'XL', 'fr-FR' => 'XL'], 'textureFilePath' => ''],
                ],
            ],
            [
                'attributeGroupId' => 2,
                'names' => ['en-US' => 'Color', 'fr-FR' => 'Couleur'],
                'publicNames' => ['en-US' => 'Color', 'fr-FR' => 'Couleur'],
                'type' => 'color',
                'colorGroup' => true,
                'position' => 1,
                'attributes' => [
                    ['attributeId' => 5, 'position' => 0, 'color' => '#F5F5DC', 'localizedNames' => ['en-US' => 'Beige', 'fr-FR' => 'Beige'], 'textureFilePath' => ''],
                    ['attributeId' => 6, 'position' => 1, 'color' => '#FFFFFF', 'localizedNames' => ['en-US' => 'White', 'fr-FR' => 'Blanc'], 'textureFilePath' => ''],
                    ['attributeId' => 7, 'position' => 2, 'color' => '#FAEBD7', 'localizedNames' => ['en-US' => 'Off White', 'fr-FR' => 'Blanc cassé'], 'textureFilePath' => ''],
                    ['attributeId' => 8, 'position' => 3, 'color' => '#A2A2A2', 'localizedNames' => ['en-US' => 'Gray', 'fr-FR' => 'Gris'], 'textureFilePath' => ''],
                    ['attributeId' => 9, 'position' => 4, 'color' => '#5F5F5F', 'localizedNames' => ['en-US' => 'Taupe', 'fr-FR' => 'Taupe'], 'textureFilePath' => ''],
                    ['attributeId' => 10, 'position' => 5, 'color' => '#434A54', 'localizedNames' => ['en-US' => 'Black', 'fr-FR' => 'Noir'], 'textureFilePath' => ''],
                    ['attributeId' => 11, 'position' => 6, 'color' => '#F39C11', 'localizedNames' => ['en-US' => 'Orange', 'fr-FR' => 'Orange'], 'textureFilePath' => ''],
                    ['attributeId' => 12, 'position' => 7, 'color' => '#E84C3D', 'localizedNames' => ['en-US' => 'Red', 'fr-FR' => 'Rouge'], 'textureFilePath' => ''],
                    ['attributeId' => 13, 'position' => 8, 'color' => '#9B59B6', 'localizedNames' => ['en-US' => 'Fuchsia', 'fr-FR' => 'Fuchsia'], 'textureFilePath' => ''],
                    ['attributeId' => 14, 'position' => 9, 'color' => '#F3CFDE', 'localizedNames' => ['en-US' => 'Pink', 'fr-FR' => 'Rose'], 'textureFilePath' => ''],
                    ['attributeId' => 15, 'position' => 10, 'color' => '#59AB5C', 'localizedNames' => ['en-US' => 'Green', 'fr-FR' => 'Vert'], 'textureFilePath' => ''],
                    ['attributeId' => 16, 'position' => 11, 'color' => '#F1C40F', 'localizedNames' => ['en-US' => 'Yellow', 'fr-FR' => 'Jaune'], 'textureFilePath' => ''],
                    ['attributeId' => 17, 'position' => 12, 'color' => '#935116', 'localizedNames' => ['en-US' => 'Brown', 'fr-FR' => 'Marron'], 'textureFilePath' => ''],
                    ['attributeId' => 18, 'position' => 13, 'color' => '#C68E17', 'localizedNames' => ['en-US' => 'Camel', 'fr-FR' => 'Camel'], 'textureFilePath' => ''],
                ],
            ],
            [
                'attributeGroupId' => 3,
                'names' => ['en-US' => 'Dimension', 'fr-FR' => 'Dimension'],
                'publicNames' => ['en-US' => 'Dimension', 'fr-FR' => 'Dimension'],
                'type' => 'select',
                'colorGroup' => false,
                'position' => 2,
                'attributes' => [
                    ['attributeId' => 22, 'position' => 0, 'color' => '', 'localizedNames' => ['en-US' => '40x60cm', 'fr-FR' => '40x60cm'], 'textureFilePath' => ''],
                    ['attributeId' => 23, 'position' => 1, 'color' => '', 'localizedNames' => ['en-US' => '60x90cm', 'fr-FR' => '60x90cm'], 'textureFilePath' => ''],
                    ['attributeId' => 24, 'position' => 2, 'color' => '', 'localizedNames' => ['en-US' => '80x120cm', 'fr-FR' => '80x120cm'], 'textureFilePath' => ''],
                ],
            ],
            [
                'attributeGroupId' => 4,
                'names' => ['en-US' => 'Paper Type', 'fr-FR' => 'Type de papier'],
                'publicNames' => ['en-US' => 'Paper Type', 'fr-FR' => 'Type de papier'],
                'type' => 'radio',
                'colorGroup' => false,
                'position' => 3,
                'attributes' => [
                    ['attributeId' => 25, 'position' => 0, 'color' => '', 'localizedNames' => ['en-US' => 'Standard', 'fr-FR' => 'Standard'], 'textureFilePath' => ''],
                    ['attributeId' => 26, 'position' => 1, 'color' => '', 'localizedNames' => ['en-US' => 'Recycled', 'fr-FR' => 'Recyclé'], 'textureFilePath' => ''],
                    ['attributeId' => 27, 'position' => 2, 'color' => '', 'localizedNames' => ['en-US' => 'Glossy', 'fr-FR' => 'Brillant'], 'textureFilePath' => ''],
                    ['attributeId' => 28, 'position' => 3, 'color' => '', 'localizedNames' => ['en-US' => 'Matte', 'fr-FR' => 'Mat'], 'textureFilePath' => ''],
                ],
            ],
        ];
    }
}
