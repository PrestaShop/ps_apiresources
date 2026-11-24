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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CatalogPriceRule;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\CatalogPriceRule\Dto\CatalogPriceRuleReduction;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\CleanHtml;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Command\AddCatalogPriceRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Command\DeleteCatalogPriceRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Command\EditCatalogPriceRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Exception\CatalogPriceRuleException;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Exception\CatalogPriceRuleNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Query\GetCatalogPriceRuleForEditing;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainConstraintException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/catalog-price-rules',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCatalogPriceRuleCommand::class,
            CQRSQuery: GetCatalogPriceRuleForEditing::class,
            scopes: ['catalog_price_rule_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
            openapi: new OpenApiOperation(
                summary: 'Create a catalog price rule.',
                description: 'Creates the rule and applies it to the products right away. Every field is required '
                    . 'except dateFrom and dateTo. The creation command takes the reduction value as a string: send '
                    . 'reduction.value as a JSON string ("15.5"), a JSON number answers 400; the update accepts '
                    . 'both, and the response returns a number. A dateTo before dateFrom answers 422.',
            ),
        ),
        new CQRSGet(
            uriTemplate: '/catalog-price-rules/{catalogPriceRuleId}',
            requirements: ['catalogPriceRuleId' => '\d+'],
            CQRSQuery: GetCatalogPriceRuleForEditing::class,
            scopes: ['catalog_price_rule_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/catalog-price-rules/{catalogPriceRuleId}',
            requirements: ['catalogPriceRuleId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditCatalogPriceRuleCommand::class,
            CQRSQuery: GetCatalogPriceRuleForEditing::class,
            scopes: ['catalog_price_rule_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            openapi: new OpenApiOperation(
                summary: 'Update a catalog price rule.',
                description: 'Partial update: only the sent fields change, and the rule is applied again to the '
                    . 'products. The reduction is sent whole (type and value). A date cannot be removed, only '
                    . 'replaced. From PrestaShop 9.2.2 the resulting date range (the dates sent, the stored ones '
                    . 'otherwise) answers 422 when dateTo is before dateFrom; older versions only compare a new '
                    . 'dateFrom with the stored dateTo, so they refuse a valid range moved after the stored end '
                    . 'date and store an inverse range when only dateTo is sent. An unknown rule answers 422 instead '
                    . 'of 404: the core edit handler does not check that the rule exists.',
            ),
        ),
        new CQRSDelete(
            uriTemplate: '/catalog-price-rules/{catalogPriceRuleId}',
            requirements: ['catalogPriceRuleId' => '\d+'],
            CQRSCommand: DeleteCatalogPriceRuleCommand::class,
            scopes: ['catalog_price_rule_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        // The first matching class wins: CatalogPriceRuleNotFoundException extends CatalogPriceRuleException
        CatalogPriceRuleNotFoundException::class => Response::HTTP_NOT_FOUND,
        // Invalid id or date, inverse date range (CatalogPriceRuleConstraintException), values the legacy model
        // refuses (the base exception, thrown directly by the handlers)
        CatalogPriceRuleException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // Thrown by the Reduction value object: negative value, percentage over 100
        DomainConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CatalogPriceRule
{
    #[ApiProperty(identifier: true)]
    public int $catalogPriceRuleId;

    #[ApiProperty(openapiContext: ['example' => 'Summer sales'])]
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\NotBlank(allowNull: true, groups: ['Update'])]
    #[Assert\Length(max: 255)]
    #[CleanHtml]
    public string $name;

    #[ApiProperty(openapiContext: [
        'description' => 'Shop the rule applies to. The existence of the shop is not checked.',
        'example' => 1,
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Positive]
    public int $shopId;

    #[ApiProperty(openapiContext: [
        'description' => 'Currency the rule applies to, 0 for every currency. The existence of the currency is not '
            . 'checked.',
        'example' => 0,
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\PositiveOrZero]
    public int $currencyId;

    #[ApiProperty(openapiContext: [
        'description' => 'Country the rule applies to, 0 for every country. The existence of the country is not '
            . 'checked.',
        'example' => 0,
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\PositiveOrZero]
    public int $countryId;

    #[ApiProperty(openapiContext: [
        'description' => 'Customer group the rule applies to, 0 for every group. The existence of the group is not '
            . 'checked.',
        'example' => 0,
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\PositiveOrZero]
    public int $groupId;

    #[ApiProperty(openapiContext: [
        'description' => 'Minimum quantity of the product in the cart for the rule to apply.',
        'example' => 1,
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\PositiveOrZero]
    public int $fromQuantity;

    #[ApiProperty(openapiContext: [
        'description' => 'Price tax excluded that replaces the price of the products before the reduction, -1 to '
            . 'keep the price of each product.',
        'example' => -1,
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    public DecimalNumber $price;

    #[ApiProperty(openapiContext: [
        'description' => 'Reduction applied to the price. Sent whole on update: type and value together.',
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Valid]
    public CatalogPriceRuleReduction $reduction;

    #[ApiProperty(openapiContext: [
        'description' => 'Whether a reduction of type amount is tax included.',
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    public bool $taxIncluded;

    #[ApiProperty(openapiContext: [
        'description' => 'Start of the rule (Y-m-d H:i:s), null when the rule has no start date.',
        'example' => '2026-06-01 00:00:00',
    ])]
    public ?\DateTimeImmutable $dateFrom = null;

    #[ApiProperty(openapiContext: [
        'description' => 'End of the rule (Y-m-d H:i:s), null when the rule has no end date. Cannot be before '
            . 'dateFrom.',
        'example' => '2026-08-31 23:59:59',
    ])]
    public ?\DateTimeImmutable $dateTo = null;

    /**
     * AddCatalogPriceRuleCommand takes the reduction as two flat constructor arguments.
     */
    public const CREATE_COMMAND_MAPPING = [
        '[reduction][type]' => '[reductionType]',
        '[reduction][value]' => '[reductionValue]',
        '[taxIncluded]' => '[includeTax]',
        '[dateFrom]' => '[dateTimeFrom]',
        '[dateTo]' => '[dateTimeTo]',
    ];

    /**
     * EditCatalogPriceRuleCommand receives the reduction through setReduction(string $type, string $value), filled
     * from the reduction object by parameter name.
     */
    public const UPDATE_COMMAND_MAPPING = [
        '[taxIncluded]' => '[includeTax]',
        '[dateFrom]' => '[dateTimeFrom]',
        '[dateTo]' => '[dateTimeTo]',
    ];

    public const QUERY_MAPPING = [
        '[from]' => '[dateFrom]',
        '[to]' => '[dateTo]',
    ];
}
