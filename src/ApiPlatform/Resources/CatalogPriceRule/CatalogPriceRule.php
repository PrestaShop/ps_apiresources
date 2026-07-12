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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CatalogPriceRule;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Command\AddCatalogPriceRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Command\DeleteCatalogPriceRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Command\EditCatalogPriceRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Exception\CatalogPriceRuleConstraintException;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Exception\CatalogPriceRuleNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Query\GetCatalogPriceRuleForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/catalog-price-rules/{catalogPriceRuleId}',
            requirements: ['catalogPriceRuleId' => '\d+'],
            CQRSQuery: GetCatalogPriceRuleForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: [
                'catalog_price_rule_read',
            ],
        ),
        new CQRSCreate(
            uriTemplate: '/catalog-price-rules',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCatalogPriceRuleCommand::class,
            scopes: [
                'catalog_price_rule_write',
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/catalog-price-rules/{catalogPriceRuleId}',
            requirements: ['catalogPriceRuleId' => '\d+'],
            CQRSCommand: EditCatalogPriceRuleCommand::class,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            CQRSQuery: GetCatalogPriceRuleForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: [
                'catalog_price_rule_write',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/catalog-price-rules/{catalogPriceRuleId}',
            requirements: ['catalogPriceRuleId' => '\d+'],
            output: false,
            CQRSCommand: DeleteCatalogPriceRuleCommand::class,
            scopes: [
                'catalog_price_rule_write',
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CatalogPriceRuleNotFoundException::class => Response::HTTP_NOT_FOUND,
        CatalogPriceRuleConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CatalogPriceRule
{
    #[ApiProperty(identifier: true)]
    public int $catalogPriceRuleId;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $name;

    public int $currencyId;

    public int $countryId;

    public int $groupId;

    public int $fromQuantity;

    public int $shopId;

    public bool $includeTax;

    public DecimalNumber $price;

    #[Assert\Choice(choices: ['amount', 'percentage'], groups: ['Create'])]
    public string $reductionType;

    public DecimalNumber $reductionValue;

    public ?string $dateTimeFrom = null;

    public ?string $dateTimeTo = null;

    /**
     * GetCatalogPriceRuleForEditing returns the reduction as a nested Reduction value object
     * (type + value) and uses taxIncluded/from/to keys, so the read-back is flattened onto the
     * resource properties here.
     */
    public const QUERY_MAPPING = [
        '[reduction][type]' => '[reductionType]',
        '[reduction][value]' => '[reductionValue]',
        '[taxIncluded]' => '[includeTax]',
        '[from]' => '[dateTimeFrom]',
        '[to]' => '[dateTimeTo]',
    ];

    /**
     * AddCatalogPriceRuleCommand takes reductionType/reductionValue as flat constructor
     * arguments, but EditCatalogPriceRuleCommand exposes them through the multi-parameter
     * setter setReduction(string $type, string $value). The CQRS denormalizer fills that setter
     * from a nested "reduction" object whose keys match the parameter names, so both must be
     * provided together on a partial update.
     */
    public const UPDATE_COMMAND_MAPPING = [
        '[reductionType]' => '[reduction][type]',
        '[reductionValue]' => '[reduction][value]',
    ];
}
