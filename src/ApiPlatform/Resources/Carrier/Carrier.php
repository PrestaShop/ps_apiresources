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
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\AddCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\EditCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CannotAddCarrierException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CannotUpdateCarrierException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Query\GetCarrierForEditing;
use PrestaShop\PrestaShop\Core\Domain\Carrier\ValueObject\OutOfRangeBehavior;
use PrestaShop\PrestaShop\Core\Domain\Carrier\ValueObject\ShippingMethod;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/carriers/{carrierId}',
            requirements: ['carrierId' => '\d+'],
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/carriers',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCarrierCommand::class,
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carriers/{carrierId}',
            requirements: ['carrierId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditCarrierCommand::class,
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotAddCarrierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateCarrierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Carrier
{
    #[ApiProperty(identifier: true)]
    public int $carrierId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Length(min: 1, max: 64)]
    public string $name;

    #[LocalizedValue]
    public array $delays;

    #[Assert\NotNull(groups: ['Create'])]
    public int $grade;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $trackingUrl;

    public int $position;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    public int $maxWidth = 0;

    public int $maxHeight = 0;

    public int $maxDepth = 0;

    public DecimalNumber $maxWeight;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $associatedGroupIds;

    public bool $additionalHandlingFee;

    public bool $free;

    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Choice(choices: [ShippingMethod::BY_WEIGHT, ShippingMethod::BY_PRICE])]
    public int $shippingMethod;

    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Choice(choices: [OutOfRangeBehavior::USE_HIGHEST_RANGE, OutOfRangeBehavior::DISABLED])]
    public int $rangeBehavior;

    /**
     * Read-only: set exclusively through PATCH /carriers/{carrierId}/tax-rule-group.
     */
    public int $taxRuleGroupId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $zones;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $associatedShopIds;

    public int $ordersCount;

    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[active]' => '[enabled]',
        '[localizedDelay]' => '[delays]',
        '[hasAdditionalHandlingFee]' => '[additionalHandlingFee]',
        '[isFree]' => '[free]',
        '[idTaxRuleGroup]' => '[taxRuleGroupId]',
    ];

    public const CREATE_COMMAND_MAPPING = [
        '[delays]' => '[localizedDelay]',
        '[enabled]' => '[active]',
        '[additionalHandlingFee]' => '[hasAdditionalHandlingFee]',
        '[free]' => '[isFree]',
        '[maxWidth]' => '[max_width]',
        '[maxHeight]' => '[max_height]',
        '[maxDepth]' => '[max_depth]',
        '[maxWeight]' => '[max_weight]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        '[delays]' => '[localizedDelay]',
        '[enabled]' => '[active]',
        '[free]' => '[isFree]',
    ];
}
