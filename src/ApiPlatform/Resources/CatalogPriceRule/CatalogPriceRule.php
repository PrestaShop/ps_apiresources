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
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Command\AddCatalogPriceRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Command\DeleteCatalogPriceRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Command\EditCatalogPriceRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Exception\CannotDeleteCatalogPriceRuleException;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Exception\CannotUpdateCatalogPriceRuleException;
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
            scopes: ['catalog_price_rule_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/catalog-price-rules',
            CQRSCommand: AddCatalogPriceRuleCommand::class,
            CQRSQuery: GetCatalogPriceRuleForEditing::class,
            scopes: ['catalog_price_rule_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/catalog-price-rules/{catalogPriceRuleId}',
            requirements: ['catalogPriceRuleId' => '\d+'],
            CQRSCommand: EditCatalogPriceRuleCommand::class,
            CQRSQuery: GetCatalogPriceRuleForEditing::class,
            scopes: ['catalog_price_rule_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::EDIT_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/catalog-price-rules/{catalogPriceRuleId}',
            requirements: ['catalogPriceRuleId' => '\d+'],
            CQRSCommand: DeleteCatalogPriceRuleCommand::class,
            scopes: ['catalog_price_rule_write'],
        ),
    ],
    exceptionToStatus: [
        CatalogPriceRuleConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CatalogPriceRuleNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotDeleteCatalogPriceRuleException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateCatalogPriceRuleException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CatalogPriceRule
{
    #[ApiProperty(identifier: true)]
    public int $catalogPriceRuleId;

    #[Assert\NotBlank]
    public string $name;

    public int $shopId;

    public int $currencyId;

    public int $countryId;

    public int $groupId;

    public int $fromQuantity;

    public \PrestaShop\Decimal\DecimalNumber $price;

    public ?string $from;

    public ?string $to;

    public bool $includeTax;

    #[Assert\Choice(choices: ['amount', 'percentage'])]
    public string $reductionType;

    public string $reductionValue;

    public const QUERY_MAPPING = [
        '[reduction][type]' => '[reductionType]',
        '[reduction][value]' => '[reductionValue]',
        '[isTaxIncluded]' => '[includeTax]',
    ];

    public const COMMAND_MAPPING = [
        '[from]' => '[dateTimeFrom]',
        '[to]' => '[dateTimeTo]',
    ];

    public const EDIT_COMMAND_MAPPING = [
        '[from]' => '[dateTimeFrom]',
        '[to]' => '[dateTimeTo]',
        '[reductionType]' => '[reduction][type]',
        '[reductionValue]' => '[reduction][value]',
    ];
}
