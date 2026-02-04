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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\DiscountType;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Discount\Query\GetDiscountTypes;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/discount-types',
            CQRSQuery: GetDiscountTypes::class,
            scopes: ['discount_read'],
            CQRSQueryMapping: [],
            ApiResourceMapping: [
                '[type]' => '[type]',
                '[localizedNames]' => '[names]',
                '[localizedDescriptions]' => '[descriptions]',
                '[core]' => '[core]',
                '[enabled]' => '[enabled]',
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class DiscountTypeList
{
    #[ApiProperty(identifier: true)]
    public int $discountTypeId;

    public string $type;

    #[LocalizedValue]
    public array $names;

    #[LocalizedValue]
    public array $descriptions;

    public bool $core;

    public bool $enabled;
}
