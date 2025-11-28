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
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Stock\Query\GetCombinationStockMovements;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/combinations/{combinationId}/stock-movements',
            requirements: ['combinationId' => '\\d+'],
            CQRSQuery: GetCombinationStockMovements::class,
            scopes: [
                'product_read',
            ],
            parameters: [
                new QueryParameter(
                    key: 'limit',
                    schema: ['type' => 'integer', 'default' => 5],
                    required: false,
                    description: 'Maximum number of movements to return'
                ),
                new QueryParameter(
                    key: 'offset',
                    schema: ['type' => 'integer', 'default' => 0],
                    required: false,
                    description: 'Offset of the first movement to return'
                ),
            ],
            CQRSQueryMapping: [
                '[_context][shopId]' => '[shopId]',
                '[_context][uriVariables][combinationId]' => '[combinationId]',
            ],
        ),
    ],
    exceptionToStatus: [
        CombinationNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductCombinationStockMovement
{
    #[ApiProperty(openapiContext: ['type' => 'string', 'enum' => ['edition', 'orders']])]
    public string $type;

    /**
     * Dates keys: for 'edition' => { add: string }, for 'orders' => { from: string, to: string }
     */
    #[ApiProperty(openapiContext: ['type' => 'object'])]
    public array $dates;

    /** @var int[] */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $stockMovementIds;

    /** @var int[] */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $stockIds;

    /** @var int[] */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $orderIds;

    /** @var int[] */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $employeeIds;

    #[ApiProperty(openapiContext: ['type' => 'string', 'nullable' => true])]
    public ?string $employeeName = null;

    #[ApiProperty(openapiContext: ['type' => 'integer'])]
    public int $deltaQuantity;
}
