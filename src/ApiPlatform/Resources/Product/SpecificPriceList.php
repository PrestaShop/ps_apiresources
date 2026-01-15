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

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\Module\APIResources\ApiPlatform\Provider\SpecificPriceListProvider;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\SpecificPriceException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Query\GetSpecificPriceList;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/{productId}/specific-prices',
            requirements: ['productId' => '\d+'],
            CQRSQuery: GetSpecificPriceList::class,
            provider: SpecificPriceListProvider::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[productId]' => '[productId]',
                '[_context][langId]' => '[languageId]',
            ],
            ApiResourceMapping: [
                '[reductionValue]' => '[reductionValue]',
                '[includesTax]' => '[includesTax]',
                '[fixedPrice][value]' => '[fixedPrice]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        SpecificPriceException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class SpecificPriceList
{
    public int $productId;

    public int $specificPriceId;

    public string $reductionType;

    public DecimalNumber $reductionValue;

    public bool $includesTax;

    public ?DecimalNumber $fixedPrice = null;

    public int $fromQuantity;

    public ?\DateTimeImmutable $dateTimeFrom = null;

    public ?\DateTimeImmutable $dateTimeTo = null;

    public ?string $combinationName = null;

    public ?string $shopName = null;

    public ?string $currencyName = null;

    public ?string $currencyISOCode = null;

    public ?string $countryName = null;

    public ?string $groupName = null;

    public ?string $customerName = null;
}
