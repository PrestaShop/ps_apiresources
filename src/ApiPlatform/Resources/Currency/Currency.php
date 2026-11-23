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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\AddCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\AddUnofficialCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\DeleteCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\EditCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\EditUnofficialCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\ToggleCurrencyStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CannotUpdateCurrencyException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyException;
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
            CQRSQuery: GetCurrencyForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
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
            validationContext: ['groups' => ['Default', 'Update', 'UpdateOfficial']],
            CQRSCommand: EditCurrencyCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetCurrencyForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['currency_write'],
            openapi: new OpenApiOperation(
                summary: 'Update a currency.',
                description: 'Partial update of an official currency. enabled is required: the core applies it on '
                    . 'every edit and would otherwise disable the currency. The ISO code of an official currency '
                    . 'cannot be changed (422).',
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/currencies/{currencyId}/toggle-status',
            requirements: ['currencyId' => '\d+'],
            allowEmptyBody: true,
            CQRSCommand: ToggleCurrencyStatusCommand::class,
            CQRSQuery: GetCurrencyForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['currency_write'],
            openapi: new OpenApiOperation(
                summary: 'Toggle the status of a currency.',
                description: 'Flips the enabled status without a body and returns the updated currency. To set a '
                    . 'given status, send enabled to PATCH /currencies/{currencyId}.',
            ),
        ),
        // Unofficial currencies are the same structure, created and edited through their own
        // commands because the core does not resolve them against the CLDR reference data.
        new CQRSCreate(
            uriTemplate: '/currencies/unofficials',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddUnofficialCurrencyCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetCurrencyForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['currency_write'],
            openapi: new OpenApiOperation(
                summary: 'Create an unofficial currency.',
                description: 'Creates a currency that is not resolved against the CLDR reference data, with any '
                    . 'ISO code.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/currencies/unofficials/{currencyId}',
            requirements: ['currencyId' => '\d+'],
            read: false,
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditUnofficialCurrencyCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetCurrencyForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['currency_write'],
            openapi: new OpenApiOperation(
                summary: 'Update an unofficial currency.',
                description: 'Partial update of an unofficial currency, including its ISO code. enabled is required: '
                    . 'the core applies it on every edit and would otherwise disable the currency.',
            ),
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CurrencyNotFoundException::class => Response::HTTP_NOT_FOUND,
        CurrencyConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateCurrencyException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CurrencyException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Currency
{
    #[ApiProperty(identifier: true)]
    public int $currencyId;

    // EditCurrencyCommand has no setIsoCode(): only an unofficial currency can change its ISO code,
    // so the official PATCH rejects the field instead of answering 200 and dropping it.
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\IsNull(groups: ['UpdateOfficial'], message: 'The ISO code of an official currency cannot be changed.')]
    public string $isoCode;

    #[ApiProperty(openapiContext: [
        'description' => 'Exchange rate against the default currency of the shop, sent as a JSON number: a numeric '
            . 'string answers 400.',
        'example' => 1.5,
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    public DecimalNumber $exchangeRate;

    // The core edit handlers apply isEnabled() on every edit and the command defaults it to false,
    // so a PATCH omitting it would disable the currency: it is required on update as well.
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\NotNull(groups: ['Update'], message: 'This value is required on every update, omitting it would disable the currency.')]
    public bool $enabled;

    public ?int $precision;

    #[LocalizedValue]
    public array $names;

    #[LocalizedValue]
    public array $symbols;

    #[LocalizedValue]
    public array $transformations;

    public bool $unofficial;

    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
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

    // EditableCurrency::isEnabled() returns an int, so coerce it through a setter
    // (same approach as the Title resource's setGender()).
    public function setEnabled(int|bool $enabled): self
    {
        $this->enabled = (bool) $enabled;

        return $this;
    }
}
