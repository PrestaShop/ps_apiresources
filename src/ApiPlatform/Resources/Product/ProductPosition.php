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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShopBundle\ApiPlatform\Metadata\PositionCollection;
use PrestaShopBundle\ApiPlatform\Metadata\UpdatePosition;

#[ApiResource(
    operations: [
        new UpdatePosition(
            uriTemplate: '/products/categories/{categoryId}/positions',
            requirements: ['categoryId' => '\d+'],
            scopes: [
                'product_write',
            ],
            positionDefinition: 'prestashop.core.grid.product.position_definition',
            parentIdField: 'categoryId',
            openapi: new OpenApiOperation(
                summary: 'Reorder the products of a category.',
                description: 'Moves the listed products of the category to their newPosition (the first position '
                    . 'is 1), the other products of the category are shifted. The products are listed in this order '
                    . 'by GET /products?orderBy=position&filters[id_category]={categoryId}. A product that is not in '
                    . 'the category answers 400.',
            ),
        ),
    ],
)]
class ProductPosition
{
    public int $categoryId;

    #[PositionCollection(rowIdField: 'productId')]
    public array $positions;
}
