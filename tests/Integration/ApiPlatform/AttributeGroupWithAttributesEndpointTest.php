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

use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class AttributeGroupWithAttributesEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        self::createApiClient(['attribute_group_read', 'attribute_group_write', 'attribute_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
        LanguageResetter::resetLanguages();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'attribute_group',
            'attribute_group_lang',
            'attribute_group_shop',
            'attribute',
            'attribute_lang',
            'attribute_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list attribute groups with attributes' => ['GET', '/attributes/groups-with-attributes'];
    }

    /**
     * Creates a controlled attribute group with two attributes (both localized
     * in en-US and fr-FR), then asserts the full structure of that group as
     * returned by /attributes/groups-with-attributes.
     *
     * The endpoint is validated on data we create in-test, not on the demo
     * fixtures: attribute IDs, colors and even group types drift between core
     * versions (9.0.3 / 9.1.x / 9.2.x / develop), so a snapshot of the demo
     * dataset cannot match every CI matrix cell.
     *
     * The whole aggregate row is compared with assertEquals so that any new
     * field on the group row or on a nested attribute row surfaces here,
     * per @jolelievre's review on PR #390. In particular every localized
     * value — including the nested `attributes[].localizedNames` — must be
     * indexed by locale, never by id_lang, and renamed into names (localized
     * prefix is dropped).
     */
    public function testListAttributeGroupsWithAttributes(): void
    {
        $groupsBefore = $this->getItem('/attributes/groups-with-attributes', ['attribute_group_read']);
        $expectedPosition = count($groupsBefore);

        $createdGroup = $this->createItem(
            '/attributes/groups',
            [
                'names' => [
                    'en-US' => 'Test WA group en',
                    'fr-FR' => 'Test WA group fr',
                ],
                'publicNames' => [
                    'en-US' => 'Test WA public en',
                    'fr-FR' => 'Test WA public fr',
                ],
                'type' => 'color',
                'shopIds' => [1],
            ],
            ['attribute_group_write']
        );
        $groupId = $createdGroup['attributeGroupId'];

        $firstAttribute = $this->createItem(
            '/attributes/attributes',
            [
                'names' => [
                    'en-US' => 'WA attr 1 en',
                    'fr-FR' => 'WA attr 1 fr',
                ],
                'attributeGroupId' => $groupId,
                'color' => '#123456',
                'shopIds' => [1],
            ],
            ['attribute_write']
        );
        $secondAttribute = $this->createItem(
            '/attributes/attributes',
            [
                'names' => [
                    'en-US' => 'WA attr 2 en',
                    'fr-FR' => 'WA attr 2 fr',
                ],
                'attributeGroupId' => $groupId,
                'color' => '#654321',
                'shopIds' => [1],
            ],
            ['attribute_write']
        );

        $result = $this->getItem('/attributes/groups-with-attributes', ['attribute_group_read']);

        $ourGroup = null;
        foreach ($result as $row) {
            if (isset($row['attributeGroupId']) && $row['attributeGroupId'] === $groupId) {
                $ourGroup = $row;
                break;
            }
        }
        $this->assertNotNull($ourGroup, sprintf('Expected group %d in the response.', $groupId));

        $this->assertEquals(
            [
                'attributeGroupId' => $groupId,
                'names' => [
                    'en-US' => 'Test WA group en',
                    'fr-FR' => 'Test WA group fr',
                ],
                'publicNames' => [
                    'en-US' => 'Test WA public en',
                    'fr-FR' => 'Test WA public fr',
                ],
                'type' => 'color',
                'colorGroup' => true,
                'position' => $expectedPosition,
                'attributes' => [
                    [
                        'attributeId' => $firstAttribute['attributeId'],
                        'position' => 0,
                        'color' => '#123456',
                        'names' => [
                            'en-US' => 'WA attr 1 en',
                            'fr-FR' => 'WA attr 1 fr',
                        ],
                        'textureFilePath' => null,
                    ],
                    [
                        'attributeId' => $secondAttribute['attributeId'],
                        'position' => 1,
                        'color' => '#654321',
                        'names' => [
                            'en-US' => 'WA attr 2 en',
                            'fr-FR' => 'WA attr 2 fr',
                        ],
                        'textureFilePath' => null,
                    ],
                ],
            ],
            $ourGroup
        );
    }
}
