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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Order;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderProductsForViewing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/orders/{orderId}/products',
            requirements: ['orderId' => '\d+'],
            CQRSQuery: GetOrderProductsForViewing::class,
            scopes: ['order_read'],
            parameters: new Parameters([
                new QueryParameter(key: 'offset', required: false, description: 'Pagination offset'),
                new QueryParameter(key: 'limit', required: false, description: 'Pagination limit'),
                new QueryParameter(key: 'productsSorting', required: false, description: 'ASC or DESC (default ASC)'),
            ]),
            openapiContext: [
                'parameters' => [
                    ['name' => 'offset', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 0]],
                    ['name' => 'limit', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 1]],
                    ['name' => 'productsSorting', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['ASC', 'DESC']]],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class OrderProductList
{
    public ?int $orderDetailId = null;

    public int $id;

    public array $shipmentIds = [];

    public int $combinationId;

    public string $name;

    public array $packItems = [];

    public string $reference;

    public string $supplierReference;

    public string $taxRate;

    public string $type;

    public string $location;

    public int $quantity;

    public string $unitPrice;

    public string $totalPrice;

    public int $availableQuantity;

    public ?string $imagePath = null;

    public string $unitPriceTaxExclRaw;

    public string $unitPriceTaxInclRaw;

    public string $amountRefunded;

    public int $quantityRefunded;

    public string $amountRefundable;

    public string $amountRefundableRaw;

    public int $quantityRefundable;

    public bool $refundable;

    public ?int $orderInvoiceId = null;
}
