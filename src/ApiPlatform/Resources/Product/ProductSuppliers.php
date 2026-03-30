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
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\RemoveAllAssociatedProductSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\SetSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\UpdateProductSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Exception\ProductSupplierException;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Exception\ProductSupplierNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Query\GetAssociatedSuppliers;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Query\GetProductSupplierOptions;
use PrestaShop\PrestaShop\Core\Domain\Supplier\Exception\SupplierNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/suppliers',
            requirements: ['productId' => '\d+'],
            CQRSQuery: GetAssociatedSuppliers::class,
            scopes: [
                'product_read',
            ],
        ),
        new CQRSGet(
            uriTemplate: '/products/{productId}/supplier-options',
            requirements: ['productId' => '\d+'],
            openapiContext: ['summary' => 'Get product supplier options', 'description' => 'Retrieves all supplier options configured for a product'],
            CQRSQuery: GetProductSupplierOptions::class,
            scopes: [
                'product_read',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/suppliers',
            requirements: ['productId' => '\d+'],
            CQRSCommand: SetSuppliersCommand::class,
            CQRSQuery: GetAssociatedSuppliers::class,
            scopes: [
                'product_write',
            ],
        ),
        new CQRSCommand(
            uriTemplate: '/products/{productId}/suppliers',
            method: 'PATCH',
            requirements: ['productId' => '\d+'],
            openapiContext: ['summary' => 'Update product suppliers', 'description' => 'Updates product supplier details (reference, price, currency)'],
            CQRSCommand: UpdateProductSuppliersCommand::class,
            CQRSQuery: GetAssociatedSuppliers::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[productSuppliers]' => '[productSuppliers]',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}/suppliers',
            requirements: ['productId' => '\d+'],
            CQRSCommand: RemoveAllAssociatedProductSuppliersCommand::class,
            scopes: [
                'product_write',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductSupplierNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductSupplierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        SupplierNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductSuppliers
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 2, 3]])]
    public array $supplierIds;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'supplier_id' => ['type' => 'integer'],
                'product_supplier_id' => ['type' => 'integer'],
                'currency_id' => ['type' => 'integer'],
                'reference' => ['type' => 'string'],
                'price_tax_excluded' => ['type' => 'string'],
            ],
        ],
        'example' => [
            [
                'supplier_id' => 1,
                'product_supplier_id' => null,
                'currency_id' => 1,
                'reference' => 'SUPPLIER-REF-123',
                'price_tax_excluded' => '10.50',
            ],
        ],
    ])]
    public array $productSuppliers;
}
