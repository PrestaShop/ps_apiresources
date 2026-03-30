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
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\AddCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\DeleteCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\EditCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\ToggleCarrierStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CannotAddCarrierException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CannotDeleteCarrierException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CannotToggleCarrierStatusException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CannotUpdateCarrierException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Query\GetCarrierForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
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
            CQRSCommand: AddCarrierCommand::class,
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carriers/{carrierId}',
            requirements: ['carrierId' => '\d+'],
            CQRSCommand: EditCarrierCommand::class,
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::EDIT_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/carriers/{carrierId}',
            requirements: ['carrierId' => '\d+'],
            CQRSCommand: DeleteCarrierCommand::class,
            scopes: ['carrier_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/carriers/{carrierId}/toggle-status',
            requirements: ['carrierId' => '\d+'],
            output: false,
            CQRSCommand: ToggleCarrierStatusCommand::class,
            scopes: ['carrier_write'],
        ),
    ],
    exceptionToStatus: [
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotAddCarrierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateCarrierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteCarrierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotToggleCarrierStatusException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Carrier
{
    #[ApiProperty(identifier: true)]
    public int $carrierId;

    #[Assert\NotBlank]
    public string $name;

    public int $grade;

    public string $trackingUrl;

    public int $position;

    public bool $enabled;

    #[LocalizedValue]
    public array $delay;

    public int $maxWidth;

    public int $maxHeight;

    public int $maxDepth;

    public \PrestaShop\Decimal\DecimalNumber $maxWeight;

    /**
     * @var int[]
     */
    public array $groupIds;

    public bool $hasAdditionalHandlingFee;

    public bool $free;

    public int $shippingMethod;

    public int $taxRuleGroupId;

    public int $rangeBehavior;

    /**
     * @var int[]
     */
    public array $shopIds;

    /**
     * @var int[]
     */
    public array $zones;

    public ?string $logoPath;

    public int $ordersCount;

    public const QUERY_MAPPING = [
        '[isActive]' => '[active]',
        '[localizedDelay]' => '[delay]',
        '[max_width]' => '[maxWidth]',
        '[max_height]' => '[maxHeight]',
        '[max_depth]' => '[maxDepth]',
        '[max_weight]' => '[maxWeight]',
        '[associatedGroupIds]' => '[groupIds]',
        '[idTaxRuleGroup]' => '[taxRuleGroupId]',
        '[associatedShopIds]' => '[shopIds]',
    ];

    public const COMMAND_MAPPING = [
        '[delay]' => '[localizedDelay]',
        '[maxWidth]' => '[max_width]',
        '[maxHeight]' => '[max_height]',
        '[maxDepth]' => '[max_depth]',
        '[maxWeight]' => '[max_weight]',
        '[groupIds]' => '[associatedGroupIds]',
        '[shopIds]' => '[associatedShopIds]',
    ];

    public const EDIT_COMMAND_MAPPING = [
        '[delay]' => '[localizedDelay]',
        '[maxWidth]' => '[max_width]',
        '[maxHeight]' => '[max_height]',
        '[maxDepth]' => '[max_depth]',
        '[maxWeight]' => '[max_weight]',
        '[groupIds]' => '[associatedGroupIds]',
        '[taxRuleGroupId]' => '[idTaxRuleGroup]',
        '[shopIds]' => '[associatedShopIds]',
        '[hasAdditionalHandlingFee]' => '[additionalHandlingFee]',
    ];
}
