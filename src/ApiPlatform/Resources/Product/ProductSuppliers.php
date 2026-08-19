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
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\RemoveAllAssociatedProductSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\SetProductDefaultSupplierCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\SetSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\UpdateProductSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Exception\ProductSupplierException;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Query\GetProductSupplierOptions;
use PrestaShop\PrestaShop\Core\Domain\Supplier\Exception\SupplierException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/suppliers',
            requirements: ['productId' => '\d+'],
            CQRSQuery: GetProductSupplierOptions::class,
            scopes: ['product_read'],
        ),
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/suppliers',
            requirements: ['productId' => '\d+'],
            CQRSCommand: SetSuppliersCommand::class,
            CQRSQuery: GetProductSupplierOptions::class,
            validationContext: ['groups' => ['Default', 'SetSuppliers']],
            scopes: ['product_write'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/products/{productId}/suppliers',
            requirements: ['productId' => '\d+'],
            read: false,
            CQRSCommand: UpdateProductSuppliersCommand::class,
            CQRSCommandMapping: [
                '[productSuppliers][@index][productSupplierId]' => '[productSuppliers][@index][product_supplier_id]',
                '[productSuppliers][@index][supplierId]' => '[productSuppliers][@index][supplier_id]',
                '[productSuppliers][@index][currencyId]' => '[productSuppliers][@index][currency_id]',
                '[productSuppliers][@index][priceTaxExcluded]' => '[productSuppliers][@index][price_tax_excluded]',
            ],
            CQRSQuery: GetProductSupplierOptions::class,
            validationContext: ['groups' => ['Default', 'UpdateSuppliers']],
            scopes: ['product_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}/suppliers',
            requirements: ['productId' => '\d+'],
            output: false,
            CQRSCommand: RemoveAllAssociatedProductSuppliersCommand::class,
            scopes: ['product_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/default-supplier',
            requirements: ['productId' => '\d+'],
            read: false,
            CQRSCommand: SetProductDefaultSupplierCommand::class,
            CQRSQuery: GetProductSupplierOptions::class,
            validationContext: ['groups' => ['Default', 'SetDefaultSupplier']],
            scopes: ['product_write'],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ProductSupplierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // The supplier ids are validated by the Supplier domain (distinct from Product\Supplier)
        SupplierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CurrencyConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductSuppliers
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    /**
     * Supplier id used as the product default.
     */
    #[Assert\NotBlank(groups: ['SetDefaultSupplier'])]
    #[Assert\Positive(groups: ['SetDefaultSupplier'])]
    public int $defaultSupplierId;

    /**
     * Supplier ids associated with the product.
     *
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1]])]
    #[Assert\NotBlank(groups: ['SetSuppliers'])]
    #[Assert\All([new Assert\Positive()], groups: ['SetSuppliers'])]
    public array $supplierIds;

    /**
     * Product supplier associations. Returned by every operation; the PATCH operation
     * accepts the writable subset of these fields per association.
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'productSupplierId' => ['type' => 'integer'],
                'productId' => ['type' => 'integer'],
                'supplierId' => ['type' => 'integer'],
                'supplierName' => ['type' => 'string'],
                'reference' => ['type' => 'string'],
                'priceTaxExcluded' => ['type' => 'string'],
                'currencyId' => ['type' => 'integer'],
                'combinationId' => ['type' => 'integer'],
            ],
        ],
    ])]
    #[Assert\NotBlank(groups: ['UpdateSuppliers'])]
    public array $productSuppliers;
}
