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
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/carriers/{carrierId}/ranges',
            requirements: ['carrierId' => '\d+'],
            output: false,
            status: Response::HTTP_NO_CONTENT,
            CQRSCommand: SetCarrierRangesCommand::class,
            scopes: ['carrier_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CarrierRanges
{
    public int $carrierId;

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
                    'rangePrice' => ['type' => 'string'],
                ],
            ],
        ]
    )]
    public array $ranges = [];

    public const COMMAND_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[ranges][@index][zoneId]' => '[ranges][@index][id_zone]',
        '[ranges][@index][rangeFrom]' => '[ranges][@index][range_from]',
        '[ranges][@index][rangeTo]' => '[ranges][@index][range_to]',
        '[ranges][@index][rangePrice]' => '[ranges][@index][range_price]',
    ];
}
