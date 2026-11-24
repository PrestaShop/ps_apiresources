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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Query\GetCatalogPriceRuleListForProduct;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPaginate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The catalog price rules applied to one product, built on GetCatalogPriceRuleListForProduct (the price tab of
 * the product page in the back office).
 */
#[ApiResource(
    operations: [
        new CQRSPaginate(
            uriTemplate: '/products/{productId}/catalog-price-rules',
            requirements: ['productId' => '\d+'],
            CQRSQuery: GetCatalogPriceRuleListForProduct::class,
            scopes: ['catalog_price_rule_read'],
            CQRSQueryMapping: [
                '[_context][langId]' => '[langId]',
            ],
            ApiResourceMapping: [
                '[catalogPriceRuleName]' => '[name]',
                '[reduction]' => '[reductionValue]',
                '[dateStart]' => '[dateFrom]',
                '[dateEnd]' => '[dateTo]',
            ],
            itemsField: 'catalogPriceRules',
            countField: 'totalCount',
            openapi: new OpenApiOperation(
                summary: 'List the catalog price rules applied to a product.',
                description: 'Lists the catalog price rules applied to the product, sorted by catalogPriceRuleId. '
                    . 'Only limit and offset are supported: orderBy, sortOrder and filters are echoed in the '
                    . 'response but ignored, use GET /catalog-price-rules to filter or sort. The product is not '
                    . 'checked: an unknown product answers 200 and lists the rules without condition, which apply '
                    . 'to every product. The names are in the language of the context (langId '
                    . 'parameter); currencyName, currencyIso, countryName and groupName are null when the rule '
                    . 'applies to every currency, country or group. A rule without start or end date '
                    . 'returns a null dateFrom or dateTo from PrestaShop 9.2.0, -0001-11-30 00:00:00 before.',
            ),
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        // Product id 0
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductCatalogPriceRuleList
{
    public int $productId;

    public int $catalogPriceRuleId;

    public string $name;

    public ?string $shopName;

    public ?string $currencyName;

    public ?string $currencyIso;

    public ?string $countryName;

    public ?string $groupName;

    public int $fromQuantity;

    #[ApiProperty(openapiContext: ['enum' => ['amount', 'percentage']])]
    public string $reductionType;

    public DecimalNumber $reductionValue;

    public bool $taxIncluded;

    public ?\DateTimeImmutable $dateFrom;

    public ?\DateTimeImmutable $dateTo;
}
