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
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\UpdateCombinationSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetCombinationSuppliers;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Exception\ProductSupplierNotAssociatedException;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Exception\ProductSupplierNotFoundException;
use PrestaShop\PrestaShop\Core\Exception\InvalidArgumentException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/combinations/{combinationId}/suppliers',
            requirements: ['combinationId' => '\\d+'],
            CQRSQuery: GetCombinationSuppliers::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[_context][uriVariables][combinationId]' => '[combinationId]',
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/products/combinations/{combinationId}/suppliers',
            requirements: ['combinationId' => '\\d+'],
            CQRSCommand: UpdateCombinationSuppliersCommand::class,
            output: false,
            status: Response::HTTP_NO_CONTENT,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[_context][uriVariables][combinationId]' => '[combinationId]',
                '[combinationSuppliers][@index][supplierId]' => '[combinationSuppliers][@index][supplier_id]',
                '[combinationSuppliers][@index][currencyId]' => '[combinationSuppliers][@index][currency_id]',
                '[combinationSuppliers][@index][reference]' => '[combinationSuppliers][@index][reference]',
                '[combinationSuppliers][@index][priceTaxExcluded]' => '[combinationSuppliers][@index][price_tax_excluded]',
                '[combinationSuppliers][@index][productSupplierId]' => '[combinationSuppliers][@index][product_supplier_id]',
            ],
            validationContext: ['groups' => ['Default', 'Update']],
        ),
    ],
    exceptionToStatus: [
        CombinationNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductSupplierNotAssociatedException::class => Response::HTTP_NOT_FOUND,
        ProductSupplierNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidArgumentException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductCombinationSuppliers
{
    #[ApiProperty(openapiContext: ['type' => 'integer'])]
    public int $productSupplierId;

    #[ApiProperty(openapiContext: ['type' => 'integer'])]
    public int $productId;

    #[ApiProperty(openapiContext: ['type' => 'integer'])]
    public int $supplierId;

    #[ApiProperty(openapiContext: ['type' => 'string'])]
    public string $supplierName;

    #[ApiProperty(openapiContext: ['type' => 'string'])]
    public string $reference;

    public DecimalNumber $priceTaxExcluded;

    #[ApiProperty(openapiContext: ['type' => 'integer'])]
    public int $currencyId;

    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer', 'nullable' => true])]
    public ?int $combinationId = null;

    /**
     * Write-only: list of supplier associations for the PATCH operation.
     * Each item: {supplierId, currencyId, reference, priceTaxExcluded, productSupplierId?}
     *
     * @var array<int, array<string, string|int|null>>|null
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'required' => ['supplierId', 'currencyId', 'reference', 'priceTaxExcluded'],
            'properties' => [
                'supplierId' => ['type' => 'integer'],
                'currencyId' => ['type' => 'integer'],
                'reference' => ['type' => 'string'],
                'priceTaxExcluded' => ['type' => 'string', 'example' => '10.50'],
                'productSupplierId' => ['type' => 'integer', 'nullable' => true],
            ],
        ],
    ])]
    #[Assert\NotBlank(groups: ['Update'])]
    #[Assert\All(constraints: [
        new Assert\Collection(
            fields: [
                'supplierId' => [new Assert\NotBlank(), new Assert\Type('integer')],
                // Add supplier_id because after normalization both supplierId and supplier_id are present
                'supplier_id' => new Assert\Optional(new Assert\Type('integer')),
                'currencyId' => [new Assert\NotBlank(), new Assert\Type('integer')],
                // Add currency_id because after normalization both currencyId and currency_id are present
                'currency_id' => new Assert\Optional(new Assert\Type('integer')),
                'reference' => [new Assert\NotBlank(), new Assert\Type('string')],
                'priceTaxExcluded' => new Assert\NotBlank(),
                // Add price_tax_excluded because after normalization both priceTaxExcluded and price_tax_excluded are present
                'price_tax_excluded' => new Assert\Optional(),
                'productSupplierId' => new Assert\Optional(new Assert\Type('integer')),
                // Add product_supplier_id because after normalization both productSupplierId and product_supplier_id are present
                'product_supplier_id' => new Assert\Optional(new Assert\Type('integer')),
            ],
        ),
    ])]
    public ?array $combinationSuppliers = null;
}
