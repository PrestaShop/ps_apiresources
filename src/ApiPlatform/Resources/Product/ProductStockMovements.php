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
use PrestaShop\PrestaShop\Core\Domain\Product\Stock\Exception\StockAvailableNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Stock\Query\GetProductStockMovements;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/{productId}/stock-movements',
            CQRSQuery: GetProductStockMovements::class,
            scopes: ['product_read'],
            CQRSQueryMapping: [
                '[_context][shopId]' => '[shopId]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        StockAvailableNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductStockMovements
{
    public string $type;

    public bool $edition;

    public bool $fromOrders;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $stockMovementIds;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $stockIds;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $orderIds;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $employeeIds;

    public ?string $employeeName = null;

    public int $deltaQuantity;

    #[ApiProperty(openapiContext: ['type' => 'object', 'additionalProperties' => ['type' => 'string', 'format' => 'date-time']])]
    public array $dates;
}
