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
use PrestaShop\Module\APIResources\ApiPlatform\Processor\CombinationStockUpdateProcessor;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\UpdateCombinationStockAvailableCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Stock\Exception\ProductStockConstraintException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/products/combinations/{combinationId}/stocks',
            requirements: ['combinationId' => '\\d+'],
            CQRSCommand: UpdateCombinationStockAvailableCommand::class,
            processor: CombinationStockUpdateProcessor::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[_context][uriVariables][combinationId]' => '[combinationId]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductStockConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CombinationNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductCombinationStock
{
    #[ApiProperty(identifier: true)]
    public int $combinationId;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 42, 'nullable' => true])]
    public ?int $quantity = null;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 5])]
    public ?int $deltaQuantity = null;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 100])]
    public ?int $fixedQuantity = null;

    #[ApiProperty(openapiContext: ['type' => 'string', 'example' => 'Aisle 4 - Shelf B'])]
    public ?string $location = null;
}
