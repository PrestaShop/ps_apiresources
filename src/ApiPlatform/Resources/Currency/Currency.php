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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Currency;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\AddCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\DeleteCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\EditCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Query\GetCurrencyForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/currencies/{currencyId}',
            requirements: ['currencyId' => '\d+'],
            CQRSQuery: GetCurrencyForEditing::class,
            scopes: ['currency_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/currencies',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCurrencyCommand::class,
            scopes: ['currency_write'],
            CQRSQuery: GetCurrencyForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/currencies/{currencyId}',
            requirements: ['currencyId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditCurrencyCommand::class,
            CQRSQuery: GetCurrencyForEditing::class,
            scopes: ['currency_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/currencies/{currencyId}',
            requirements: ['currencyId' => '\d+'],
            CQRSCommand: DeleteCurrencyCommand::class,
            scopes: ['currency_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CurrencyNotFoundException::class => Response::HTTP_NOT_FOUND,
        CurrencyConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Currency
{
    #[ApiProperty(identifier: true)]
    public int $currencyId;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $isoCode;

    #[Assert\NotNull(groups: ['Create'])]
    public DecimalNumber $exchangeRate;

    // Names, symbols and transformations are optional: for an official currency they are
    // resolved from the CLDR reference data when not provided.
    #[LocalizedValue]
    public array $names;

    #[LocalizedValue]
    public array $symbols;

    #[LocalizedValue]
    public array $transformations;

    public int $precision;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    // Read-only: whether the currency is unofficial (not part of the CLDR reference data).
    // Unofficial currencies are managed through their own dedicated commands.
    public bool $unofficial;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[associatedShopIds]' => '[shopIds]',
    ];

    public const CREATE_COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[symbols]' => '[localizedSymbols]',
        '[transformations]' => '[localizedTransformations]',
        '[enabled]' => '[isEnabled]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[symbols]' => '[localizedSymbols]',
        '[transformations]' => '[localizedTransformations]',
        '[enabled]' => '[isEnabled]',
    ];
}
