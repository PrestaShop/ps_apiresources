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
use PrestaShop\Module\APIResources\ApiPlatform\Processor\CombinationSuppliersUpdateProcessor;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\UpdateCombinationSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetCombinationSuppliers;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Exception\ProductSupplierNotAssociatedException;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Exception\ProductSupplierNotFoundException;
use PrestaShop\PrestaShop\Core\Exception\InvalidArgumentException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;

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
            CQRSQuery: GetCombinationSuppliers::class,
            processor: CombinationSuppliersUpdateProcessor::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[_context][uriVariables][combinationId]' => '[combinationId]',
            ],
            CQRSQueryMapping: [
                '[_context][uriVariables][combinationId]' => '[combinationId]',
            ],
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

    #[ApiProperty(openapiContext: ['type' => 'string', 'example' => '12.340'])]
    public string $priceTaxExcluded;

    #[ApiProperty(openapiContext: ['type' => 'integer'])]
    public int $currencyId;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'nullable' => true])]
    public ?int $combinationId = null;
}
