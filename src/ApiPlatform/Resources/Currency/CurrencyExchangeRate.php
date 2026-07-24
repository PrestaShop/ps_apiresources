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
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\ExchangeRateNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Query\GetCurrencyExchangeRate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/currencies/exchange-rates',
            CQRSQuery: GetCurrencyExchangeRate::class,
            scopes: ['currency_read'],
            CQRSQueryMapping: [
                '[_queryResult]' => '[exchangeRate]',
            ],
            parameters: new Parameters([
                new QueryParameter(
                    key: 'isoCode',
                    required: true,
                    description: 'Currency ISO code (e.g. USD, EUR)'
                ),
            ]),
            openapiContext: [
                'parameters' => [
                    ['name' => 'isoCode', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3]],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        ExchangeRateNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CurrencyExchangeRate
{
    #[ApiProperty(identifier: true)]
    public string $isoCode;

    public DecimalNumber $exchangeRate;
}
