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
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\SetCarrierTaxRuleGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/carriers/{carrierId}/ranges',
            requirements: ['carrierId' => '\d+'],
            openapiContext: ['summary' => 'Set carrier ranges', 'description' => 'Sets the delivery ranges and prices for a carrier'],
            CQRSCommand: SetCarrierRangesCommand::class,
            scopes: [
                'carrier_write',
            ],
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/carriers/{carrierId}/tax-rule-groups',
            requirements: ['carrierId' => '\d+'],
            openapiContext: ['summary' => 'Set carrier tax rule group', 'description' => 'Sets the tax rule group for a carrier'],
            CQRSCommand: SetCarrierTaxRuleGroupCommand::class,
            scopes: [
                'carrier_write',
            ],
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
        ),
    ],
    exceptionToStatus: [
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CarrierSettings
{
    #[ApiProperty(identifier: true)]
    public int $carrierId;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'id_zone' => ['type' => 'integer'],
                'range_from' => ['type' => 'number'],
                'range_to' => ['type' => 'number'],
                'range_price' => ['type' => 'string'],
            ],
        ],
        'example' => [
            [
                'id_zone' => 1,
                'range_from' => 0,
                'range_to' => 10,
                'range_price' => '5.00',
            ],
        ],
    ])]
    public array $ranges;

    public int $carrierTaxRuleGroupId;
}
