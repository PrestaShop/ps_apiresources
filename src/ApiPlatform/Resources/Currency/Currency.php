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
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\AddCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\DeleteCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\EditCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\ToggleCurrencyStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Query\GetCurrencyForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/currencies',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCurrencyCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            scopes: ['currency_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/currencies/{currencyId}',
            requirements: ['currencyId' => '\d+'],
            output: false,
            CQRSCommand: DeleteCurrencyCommand::class,
            scopes: ['currency_write'],
        ),
        new CQRSGet(
            uriTemplate: '/currencies/{currencyId}',
            requirements: ['currencyId' => '\d+'],
            CQRSQuery: GetCurrencyForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['currency_read'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/currencies/{currencyId}',
            requirements: ['currencyId' => '\d+'],
            read: false,
            CQRSCommand: EditCurrencyCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetCurrencyForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['currency_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/currencies/{currencyId}/toggle-status',
            requirements: ['currencyId' => '\d+'],
            output: false,
            allowEmptyBody: true,
            CQRSCommand: ToggleCurrencyStatusCommand::class,
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
    public float $exchangeRate;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    public ?int $precision;

    #[LocalizedValue]
    public array $names;

    #[LocalizedValue]
    public array $symbols;

    #[LocalizedValue]
    public array $transformations;

    public bool $unofficial;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $shopIds;

    // AddCurrencyCommand / EditCurrencyCommand use isEnabled + localized* field names
    public const COMMAND_MAPPING = [
        '[enabled]' => '[isEnabled]',
        '[names]' => '[localizedNames]',
        '[symbols]' => '[localizedSymbols]',
        '[transformations]' => '[localizedTransformations]',
    ];

    // EditableCurrency exposes associatedShopIds; the localized + isoCode fields map by name
    public const QUERY_MAPPING = [
        '[associatedShopIds]' => '[shopIds]',
    ];
}
