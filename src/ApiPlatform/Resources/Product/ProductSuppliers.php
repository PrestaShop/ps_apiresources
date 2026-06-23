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
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\SetProductDefaultSupplierCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\SetSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Exception\ProductSupplierException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/suppliers',
            requirements: ['productId' => '\d+'],
            output: false,
            CQRSCommand: SetSuppliersCommand::class,
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
            uriTemplate: '/products/{productId}/default-suppliers',
            requirements: ['productId' => '\d+'],
            output: false,
            CQRSCommand: SetProductDefaultSupplierCommand::class,
            scopes: ['product_write'],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductSupplierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductSuppliers
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    /**
     * Supplier ids to associate with the product (used by the suppliers endpoint).
     *
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1]])]
    public array $supplierIds;

    /**
     * Supplier id to set as the product default (used by the default-suppliers endpoint).
     */
    public int $defaultSupplierId;
}
