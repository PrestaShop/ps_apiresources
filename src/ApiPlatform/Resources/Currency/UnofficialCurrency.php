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
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\AddUnofficialCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/currencies/unofficials',
            CQRSCommand: AddUnofficialCurrencyCommand::class,
            scopes: ['currency_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CurrencyConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CurrencyException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class UnofficialCurrency
{
    #[ApiProperty(identifier: true)]
    public int $currencyId;

    #[Assert\NotBlank]
    public string $isoCode;

    #[Assert\NotBlank]
    public DecimalNumber $exchangeRate;

    #[Assert\Type(type: 'bool')]
    public bool $enabled;

    public const COMMAND_MAPPING = [
        '[enabled]' => '[isEnabled]',
    ];
}
