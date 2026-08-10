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
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\UpdateProductSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Exception\ProductSupplierException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/supplier-details',
            requirements: ['productId' => '\d+'],
            output: false,
            CQRSCommand: UpdateProductSuppliersCommand::class,
            scopes: ['product_write'],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductSupplierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductSupplierDetails
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    /**
     * The per-supplier details (reference and price) for suppliers already associated with the product.
     *
     * @var array<int, array{supplier_id: int, currency_id: int, reference: string, price_tax_excluded: string, product_supplier_id?: int}>
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => ['type' => 'object'],
        'example' => [['supplier_id' => 1, 'currency_id' => 1, 'reference' => 'SUP-REF-1', 'price_tax_excluded' => '12.500000']],
    ])]
    public array $productSuppliers;
}
