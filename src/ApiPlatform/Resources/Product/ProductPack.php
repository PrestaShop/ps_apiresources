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
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Command\RemoveAllProductsFromPackCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Command\SetPackProductsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Exception\ProductPackConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Exception\ProductPackException;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Query\GetPackedProducts;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\ShopAssociationNotFound;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/packs',
            requirements: ['productId' => '\d+'],
            CQRSQuery: GetPackedProducts::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[packId]' => '[productId]',
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[_context][langId]' => '[languageId]',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/packs',
            requirements: ['productId' => '\d+'],
            CQRSCommand: SetPackProductsCommand::class,
            CQRSQuery: GetPackedProducts::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[productId]' => '[packId]',
            ],
            CQRSQueryMapping: [
                '[packId]' => '[productId]',
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[_context][langId]' => '[languageId]',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}/packs',
            requirements: ['productId' => '\d+'],
            CQRSCommand: RemoveAllProductsFromPackCommand::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[productId]' => '[packId]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductPackException::class => Response::HTTP_NOT_FOUND,
        ProductPackConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ShopAssociationNotFound::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductPack
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'productId' => ['type' => 'integer'],
                'quantity' => ['type' => 'integer'],
                'combinationId' => ['type' => 'integer'],
                'productName' => ['type' => 'string'],
                'reference' => ['type' => 'string'],
                'imageUrl' => ['type' => 'string'],
            ],
        ],
        'example' => [
            [
                'productId' => 1,
                'quantity' => 2,
                'combinationId' => 0,
                'productName' => 'Product Name',
                'reference' => 'Ref: ABC123',
                'imageUrl' => 'https://example.com/image.jpg',
            ],
        ],
    ])]
    public array $packedProducts;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'product_id' => ['type' => 'integer'],
                'quantity' => ['type' => 'integer'],
                'combination_id' => ['type' => 'integer'],
            ],
        ],
        'example' => [
            [
                'product_id' => 1,
                'quantity' => 2,
                'combination_id' => 0,
            ],
        ],
    ])]
    public array $products;
}
