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
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/{productId}/attribute-groups',
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
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
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
            'description' => 'Attributes belonging to the group. Nested `localizedNames` are keyed by language id.',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'attributeId' => ['type' => 'integer'],
                    'position' => ['type' => 'integer'],
                    'color' => ['type' => 'string'],
                    'localizedNames' => [
                        'type' => 'object',
                        'additionalProperties' => ['type' => 'string'],
                    ],
                    'textureFilePath' => ['type' => 'string', 'nullable' => true],
                ],
            ],
        ]
    )]
    public ?array $attributes;
}
