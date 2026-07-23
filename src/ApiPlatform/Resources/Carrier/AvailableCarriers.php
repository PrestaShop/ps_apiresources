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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Carrier;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\Address\Exception\AddressNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Query\GetAvailableCarriers;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/carriers/available',
            CQRSQuery: GetAvailableCarriers::class,
            scopes: ['carrier_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            parameters: new Parameters([
                new QueryParameter(
                    key: 'addressId',
                    required: true,
                    schema: ['type' => 'integer'],
                    description: 'Delivery address ID'
                ),
                new QueryParameter(
                    key: 'productQuantities',
                    required: true,
                    schema: [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'productId' => ['type' => 'integer'],
                                'quantity' => ['type' => 'integer'],
                            ],
                        ],
                    ],
                    description: 'List of products and quantities to check carrier availability for'
                ),
            ]),
        ),
    ],
    exceptionToStatus: [
        AddressNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class AvailableCarriers
{
    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer'])]
    public int|string $addressId;

    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'productId' => ['type' => 'integer'],
                    'quantity' => ['type' => 'integer'],
                ],
            ],
        ]
    )]
    public array $productQuantities = [];

    public ?int $currentCarrierId = null;

    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'carrierId' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                ],
            ],
        ]
    )]
    public array $availableCarriers = [];

    public const QUERY_MAPPING = [
        '[availableCarriers][@index][id]' => '[availableCarriers][@index][carrierId]',
    ];
}
