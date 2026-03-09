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
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Query\GetCarrierForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/carriers/{carrierId}',
            requirements: ['carrierId' => '\d+'],
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Carrier
{
    #[ApiProperty(identifier: true)]
    public int $carrierId;

    public string $name;

    public int $grade;

    public string $trackingUrl;

    public int $position;

    public bool $active;

    #[LocalizedValue]
    public array $delay;

    public ?string $logoPath;

    public int $maxWidth;

    public int $maxHeight;

    public int $maxDepth;

    public DecimalNumber $maxWeight;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $associatedGroupIds;

    public bool $hasAdditionalHandlingFee;

    public bool $isFree;

    public int $shippingMethod;

    public int $idTaxRuleGroup;

    public int $rangeBehavior;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $associatedShopIds;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'object']])]
    public array $zones;

    public int $ordersCount;

    public const QUERY_MAPPING = [
        '[carrierId]' => '[getCarrierId]',
        '[name]' => '[getName]',
        '[grade]' => '[getGrade]',
        '[trackingUrl]' => '[getTrackingUrl]',
        '[position]' => '[getPosition]',
        '[active]' => '[isActive]',
        '[delay]' => '[getLocalizedDelay]',
        '[logoPath]' => '[getLogoPath]',
        '[maxWidth]' => '[getMaxWidth]',
        '[maxHeight]' => '[getMaxHeight]',
        '[maxDepth]' => '[getMaxDepth]',
        '[maxWeight]' => '[getMaxWeight]',
        '[associatedGroupIds]' => '[getAssociatedGroupIds]',
        '[hasAdditionalHandlingFee]' => '[hasAdditionalHandlingFee]',
        '[isFree]' => '[isFree]',
        '[shippingMethod]' => '[getShippingMethod]',
        '[idTaxRuleGroup]' => '[getIdTaxRuleGroup]',
        '[rangeBehavior]' => '[getRangeBehavior]',
        '[associatedShopIds]' => '[getAssociatedShopIds]',
        '[zones]' => '[getZones]',
        '[ordersCount]' => '[getOrdersCount]',
    ];
}
