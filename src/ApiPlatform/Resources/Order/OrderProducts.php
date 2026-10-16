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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderProductsForViewing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
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
            extraProperties: [
                'ApiResourceMapping' => [
                    '[products][@index][id]' => '[products][@index][productId]',
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class OrderProducts
{
    #[ApiProperty(identifier: true)]
    public int $orderId;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $products = [];
}
