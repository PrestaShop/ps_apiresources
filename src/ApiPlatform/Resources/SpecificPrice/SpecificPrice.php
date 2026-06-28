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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\SpecificPrice;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Command\AddSpecificPriceCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Command\DeleteSpecificPriceCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Command\EditSpecificPriceCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\SpecificPriceConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\SpecificPriceNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Query\GetSpecificPriceForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/specific-prices/{specificPriceId}',
            requirements: ['specificPriceId' => '\d+'],
            CQRSQuery: GetSpecificPriceForEditing::class,
            scopes: ['specific_price_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/specific-prices',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddSpecificPriceCommand::class,
            CQRSQuery: GetSpecificPriceForEditing::class,
            scopes: ['specific_price_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/specific-prices/{specificPriceId}',
            requirements: ['specificPriceId' => '\d+'],
            CQRSCommand: EditSpecificPriceCommand::class,
            CQRSQuery: GetSpecificPriceForEditing::class,
            scopes: ['specific_price_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/specific-prices/{specificPriceId}',
            requirements: ['specificPriceId' => '\d+'],
            CQRSCommand: DeleteSpecificPriceCommand::class,
            scopes: ['specific_price_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        SpecificPriceNotFoundException::class => Response::HTTP_NOT_FOUND,
        SpecificPriceConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class SpecificPrice
{
    #[ApiProperty(identifier: true)]
    public int $specificPriceId;

    #[Assert\NotNull(groups: ['Create'])]
    public int $productId;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $reductionType;

    #[Assert\NotNull(groups: ['Create'])]
    public DecimalNumber $reductionValue;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $includeTax;

    #[Assert\NotNull(groups: ['Create'])]
    public DecimalNumber $fixedPrice;

    #[Assert\NotNull(groups: ['Create'])]
    public int $fromQuantity;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $dateTimeFrom;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $dateTimeTo;

    public ?int $shopId = null;

    public ?int $combinationId = null;

    public ?int $currencyId = null;

    public ?int $countryId = null;

    public ?int $groupId = null;

    public ?int $customerId = null;

    public const QUERY_MAPPING = [
        '[reductionAmount]' => '[reductionValue]',
        '[includesTax]' => '[includeTax]',
        '[fixedPrice][value]' => '[fixedPrice]',
    ];

    // AddSpecificPriceCommand takes reductionType/reductionValue as flat constructor args, while
    // EditSpecificPriceCommand exposes them through the multi-parameter setter
    // setReduction(string $type, string $value). The mapper fills that setter from a nested
    // "reduction" object whose keys match the parameter names. setIncludesTax(bool $includesTax)
    // likewise expects the "includesTax" key.
    public const UPDATE_COMMAND_MAPPING = [
        '[reductionType]' => '[reduction][reductionType]',
        '[reductionValue]' => '[reduction][reductionValue]',
        '[includeTax]' => '[includesTax]',
    ];
}
