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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Attribute;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Query\GetAttributeGroupList;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/attributes/groups-with-attributes',
            CQRSQuery: GetAttributeGroupList::class,
            scopes: ['attribute_group_read'],
            CQRSQueryMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
        ),
    ],
)]
class AttributeGroupWithAttributes
{
    public int $attributeGroupId;

    #[LocalizedValue]
    public array $localizedNames;

    #[LocalizedValue]
    public array $localizedPublicNames;

    public string $groupType;

    public bool $colorGroup;

    public int $position;

    /**
     * Per-group attribute list. Each item: {attributeId, position, color, name, imagePath}.
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'attributeId' => ['type' => 'integer'],
                'position' => ['type' => 'integer'],
                'color' => ['type' => 'string', 'nullable' => true],
                'name' => ['type' => 'string'],
                'imagePath' => ['type' => 'string', 'nullable' => true],
            ],
        ],
    ])]
    public array $attributes;
}
