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
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\EditUnofficialCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CannotUpdateCurrencyException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/currencies/unofficials/{currencyId}',
            requirements: ['currencyId' => '\d+'],
            read: false,
            CQRSCommand: EditUnofficialCurrencyCommand::class,
            scopes: ['currency_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CurrencyNotFoundException::class => Response::HTTP_NOT_FOUND,
        CurrencyConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateCurrencyException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CurrencyException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class EditUnofficialCurrency
{
    #[ApiProperty(identifier: true)]
    public int $currencyId;

    public ?string $isoCode = null;

    public ?DecimalNumber $exchangeRate = null;

    public int|string|null $precision = null;

    #[ApiProperty(openapiContext: ['type' => 'object'])]
    public ?array $localizedNames = null;

    #[ApiProperty(openapiContext: ['type' => 'object'])]
    public ?array $localizedSymbols = null;

    #[ApiProperty(openapiContext: ['type' => 'object'])]
    public ?array $localizedTransformations = null;

    public ?bool $enabled = null;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public ?array $shopIds = null;

    public const COMMAND_MAPPING = [
        '[enabled]' => '[isEnabled]',
    ];
}
