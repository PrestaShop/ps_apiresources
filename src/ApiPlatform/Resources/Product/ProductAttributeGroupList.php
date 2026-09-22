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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Product\AttributeGroup\Query\GetProductAttributeGroups;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/{productId}/attribute-groups',
            requirements: ['productId' => '\d+'],
            // The whole list is returned by the CQRS query at once, so the page parameter ApiPlatform
            // documents by default on every collection operation would be misleading
            paginationEnabled: false,
            CQRSQuery: GetProductAttributeGroups::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
            ApiResourceMapping: [
                '[localizedNames]' => '[names]',
                '[localizedPublicNames]' => '[publicNames]',
                '[isColorGroup]' => '[colorGroup]',
                '[groupType]' => '[type]',
            ],
        ),
    ],
    exceptionToStatus: [
        // Thrown by the ProductId value object for an id the URI requirements let through, like 0.
        // An unknown product is not an error here: the query returns an empty list.
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductAttributeGroupList
{
    public int $attributeGroupId;

    #[LocalizedValue]
    public array $names;

    #[LocalizedValue]
    public array $publicNames;

    public string $type;

    public bool $colorGroup;

    public int $position;

    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'description' => 'Attributes of the group used by the product combinations. Nested `names` are keyed by language locale.',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'attributeId' => ['type' => 'integer'],
                    'position' => ['type' => 'integer'],
                    'color' => ['type' => 'string', 'description' => 'Hexadecimal color, empty when the group is not a color group.'],
                    'names' => [
                        'type' => 'object',
                        'description' => 'Attribute names keyed by language locale.',
                        'example' => [
                            'en-US' => 'M',
                            'fr-FR' => 'M',
                        ],
                    ],
                    'textureFilePath' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'example' => [
                [
                    'attributeId' => 1,
                    'position' => 0,
                    'color' => '',
                    'names' => [
                        'en-US' => 'S',
                        'fr-FR' => 'S',
                    ],
                    'textureFilePath' => null,
                ],
                [
                    'attributeId' => 2,
                    'position' => 1,
                    'color' => '',
                    'names' => [
                        'en-US' => 'M',
                        'fr-FR' => 'M',
                    ],
                    'textureFilePath' => null,
                ],
            ],
        ]
    )]
    public ?array $attributes;
}
