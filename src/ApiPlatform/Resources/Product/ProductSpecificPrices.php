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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Query\GetSpecificPriceList;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/specific-prices',
            requirements: ['productId' => '\d+'],
            CQRSQuery: GetSpecificPriceList::class,
            scopes: [
                'specific_price_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
)]
class ProductSpecificPrices
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    public array $specificPrices;

    public int $totalSpecificPricesCount;

    public const QUERY_MAPPING = [
        '[_context][langId]' => '[languageId]',
        '[productId]' => '[productId]',
    ];
}
