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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\Address\Exception\AddressNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Query\GetAvailableCarriers;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/carriers/search-compatible-carriers',
            CQRSQuery: GetAvailableCarriers::class,
            scopes: ['carrier_read'],
            // The scalar constructor of GetAvailableCarriers, required to build the query from
            // the request parameters, only exists since PrestaShop 9.2.0 (PrestaShop/PrestaShop#42022)
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            openapi: new OpenApiOperation(
                summary: 'Search the carriers compatible with a delivery context.',
                description: 'Returns the carriers that can deliver the requested products, in the requested '
                    . 'quantities, to the requested address. A carrier is compatible when it handles every '
                    . 'product of the list and when it covers the zone of the address country and state. '
                    . 'Typically used while building a cart or an order to offer the shipping choices.',
            ),
            parameters: new Parameters([
                new QueryParameter(
                    key: 'addressId',
                    required: true,
                    schema: ['type' => 'integer'],
                    description: 'Identifier of the delivery address the products must be shipped to. Its country, '
                        . 'state and zone determine which carriers are compatible: a carrier that does not cover the '
                        . 'zone of this address is excluded from the results. A customer address is expected (the one '
                        . 'selected on the cart or the order), and an unknown identifier returns a 404 response.'
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
                    description: 'List of products and quantities the carriers must be able to deliver'
                ),
            ]),
        ),
    ],
    exceptionToStatus: [
        AddressNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
/**
 * Carriers compatible with a delivery context: a list of products with their quantities and a
 * delivery address. Restricted search, meant to be used when building a cart or an order rather
 * than to browse the carriers of the shop (see the Carrier resource for that).
 */
class CompatibleCarriers
{
    /**
     * Identifier of the customer address the products must be shipped to. It is the criterion that
     * filters the carriers by location: only the carriers covering the zone of this address country
     * and state can deliver it.
     *
     * It is the identifier of the resource, but it is passed as a query parameter rather than as a
     * path segment, hence the string type accepted on top of the integer one: query parameters
     * always reach the resource as strings.
     */
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

    /**
     * Read-only: the carriers able to deliver the requested products to the requested address.
     */
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
    public array $compatibleCarriers = [];

    /**
     * The query result exposes the compatible carriers under availableCarriers, with an id and a
     * name per carrier, so both fields are mapped to keep them under the same target property.
     */
    public const QUERY_MAPPING = [
        '[availableCarriers][@index][id]' => '[compatibleCarriers][@index][carrierId]',
        '[availableCarriers][@index][name]' => '[compatibleCarriers][@index][name]',
    ];
}
