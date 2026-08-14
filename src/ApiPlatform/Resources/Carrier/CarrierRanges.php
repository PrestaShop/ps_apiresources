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
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\SetCarrierRangesCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Query\GetCarrierRanges;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/carriers/{carrierId}/ranges',
            requirements: ['carrierId' => '\d+'],
            CQRSQuery: GetCarrierRanges::class,
            scopes: ['carrier_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carriers/{carrierId}/ranges',
            requirements: ['carrierId' => '\d+'],
            CQRSCommand: SetCarrierRangesCommand::class,
            CQRSQuery: GetCarrierRanges::class,
            scopes: ['carrier_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
/**
 * Delivery ranges of a carrier, read and written in one shared format.
 *
 * These operations always apply to every shop: the ranges are stored globally by the Core, which
 * does not support a per-shop constraint on them yet, so the shop context parameters of the request
 * have no effect here. See QUERY_MAPPING for the details.
 */
class CarrierRanges
{
    #[ApiProperty(identifier: true)]
    public int $carrierId;

    /**
     * Delivery ranges of the carrier, each one holding the zone it applies to. Same format for
     * reading and writing: the payload sent to the update operation is the payload returned by
     * both operations, so one can be copied to build the other.
     *
     * The decimal fields are exposed as numbers, like every other decimal of the API.
     */
    #[Assert\NotBlank]
    #[Assert\Count(min: 1)]
    #[Assert\All(
        constraints: [
            new Assert\Collection(
                fields: [
                    'zoneId' => new Assert\NotBlank(),
                    'rangeFrom' => new Assert\NotBlank(),
                    'rangeTo' => new Assert\NotBlank(),
                    'rangePrice' => new Assert\NotBlank(),
                    // CQRSCommandMapping copies each field into its snake_case command
                    // counterpart without removing the original, so both are present
                    // by the time this collection is validated.
                    'id_zone' => new Assert\NotBlank(),
                    'range_from' => new Assert\NotBlank(),
                    'range_to' => new Assert\NotBlank(),
                    'range_price' => new Assert\NotBlank(),
                ],
            ),
        ],
    )]
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'zoneId' => ['type' => 'integer'],
                    'rangeFrom' => ['type' => 'number'],
                    'rangeTo' => ['type' => 'number'],
                    'rangePrice' => ['type' => 'number'],
                ],
            ],
        ]
    )]
    public ?array $ranges = null;

    /**
     * The ranges are not shop-scoped in the Core yet: CarrierRangeRepository rejects every shop
     * constraint but the all shops one (see its assertShopConstraint method and its "make shop
     * constraint magic here" todo), and it forces id_shop IS NULL on its queries. The API context
     * always builds a single shop constraint when multistore is disabled, so only the strictness of
     * that constraint is mapped: with no shopId, shopGroupId nor shopIds in the mapped data, the
     * core ShopConstraintNormalizer falls back to ShopConstraint::allShops(), which is the only
     * constraint these operations can run with.
     *
     * The GetCarrierRanges result needs no field mapping: CarrierRangesCollectionNormalizer already
     * flattens it into the ranges property.
     */
    public const QUERY_MAPPING = [
        '[_context][shopConstraint][isStrict]' => '[shopConstraint][isStrict]',
    ];

    /**
     * See QUERY_MAPPING about the shop constraint.
     */
    public const COMMAND_MAPPING = [
        '[_context][shopConstraint][isStrict]' => '[shopConstraint][isStrict]',
        '[ranges][@index][zoneId]' => '[ranges][@index][id_zone]',
        '[ranges][@index][rangeFrom]' => '[ranges][@index][range_from]',
        '[ranges][@index][rangeTo]' => '[ranges][@index][range_to]',
        '[ranges][@index][rangePrice]' => '[ranges][@index][range_price]',
    ];
}
