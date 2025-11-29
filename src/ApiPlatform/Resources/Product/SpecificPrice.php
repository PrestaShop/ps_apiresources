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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Command\AddSpecificPriceCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Command\DeleteSpecificPriceCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Command\EditSpecificPriceCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\SpecificPriceException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\SpecificPriceNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Query\GetSpecificPriceForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/specific-prices/{specificPriceId}',
            CQRSQuery: GetSpecificPriceForEditing::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: SpecificPrice::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/products/specific-prices',
            CQRSCommand: AddSpecificPriceCommand::class,
            CQRSQuery: GetSpecificPriceForEditing::class,
            scopes: [
                'product_write',
            ],
            CQRSQueryMapping: SpecificPrice::QUERY_MAPPING,
            CQRSCommandMapping: SpecificPrice::CREATE_COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/products/specific-prices/{specificPriceId}',
            CQRSCommand: EditSpecificPriceCommand::class,
            CQRSQuery: GetSpecificPriceForEditing::class,
            scopes: [
                'product_write',
            ],
            CQRSQueryMapping: SpecificPrice::QUERY_MAPPING,
            CQRSCommandMapping: SpecificPrice::UPDATE_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/products/specific-prices/{specificPriceId}',
            CQRSCommand: DeleteSpecificPriceCommand::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: SpecificPrice::DELETE_COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        SpecificPriceNotFoundException::class => Response::HTTP_NOT_FOUND,
        SpecificPriceException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class SpecificPrice
{
    #[ApiProperty(identifier: true)]
    public int $specificPriceId;

    public int $productId;

    public string $reductionType;

    public DecimalNumber $reductionValue;

    public bool $includesTax;

    public ?DecimalNumber $fixedPrice = null;

    public int $fromQuantity;

    public ?\DateTimeImmutable $dateTimeFrom = null;

    public ?\DateTimeImmutable $dateTimeTo = null;

    public ?int $combinationId = null;

    public ?int $shopId = null;

    public ?int $currencyId = null;

    public ?int $countryId = null;

    public ?int $groupId = null;

    public ?int $customerId = null;

    public ?array $customerInfo = null;

    public const QUERY_MAPPING = [
        '[specificPriceId]' => '[specificPriceId]',
        '[reductionType]' => '[reductionType]',
        // Map reductionAmount from QueryResult to reductionValue in API Resource
        '[reductionAmount]' => '[reductionValue]',
        '[includesTax]' => '[includesTax]',
        '[fixedPrice][value]' => '[fixedPrice]',
        '[fromQuantity]' => '[fromQuantity]',
        '[dateTimeFrom]' => '[dateTimeFrom]',
        '[dateTimeTo]' => '[dateTimeTo]',
        '[productId]' => '[productId]',
        '[customerInfo]' => '[customerInfo]',
        '[combinationId]' => '[combinationId]',
        '[shopId]' => '[shopId]',
        '[currencyId]' => '[currencyId]',
        '[countryId]' => '[countryId]',
        '[groupId]' => '[groupId]',
    ];

    public const CREATE_COMMAND_MAPPING = [
        '[productId]' => '[productId]',
        '[reductionType]' => '[reductionType]',
        '[reductionValue]' => '[reductionValue]',
        '[includeTax]' => '[includeTax]',
        '[fixedPrice]' => '[fixedPrice]',
        '[fromQuantity]' => '[fromQuantity]',
        '[dateTimeFrom]' => '[dateTimeFrom]',
        '[dateTimeTo]' => '[dateTimeTo]',
        '[shopId]' => '[shopId]',
        '[combinationId]' => '[combinationId]',
        '[currencyId]' => '[currencyId]',
        '[countryId]' => '[countryId]',
        '[groupId]' => '[groupId]',
        '[customerId]' => '[customerId]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        '[specificPriceId]' => '[specificPriceId]',
        // EditSpecificPriceCommand::setReduction() expects 2 args: reductionType and reductionValue
        '[reductionType]' => '[reduction][reductionType]',
        '[reductionValue]' => '[reduction][reductionValue]',
        '[includesTax]' => '[includesTax]',
        '[fixedPrice]' => '[fixedPrice]',
        '[fromQuantity]' => '[fromQuantity]',
        '[dateTimeFrom]' => '[dateTimeFrom]',
        '[dateTimeTo]' => '[dateTimeTo]',
        '[shopId]' => '[shopId]',
        '[combinationId]' => '[combinationId]',
        '[currencyId]' => '[currencyId]',
        '[countryId]' => '[countryId]',
        '[groupId]' => '[groupId]',
        '[customerId]' => '[customerId]',
    ];

    public const DELETE_COMMAND_MAPPING = [
        '[specificPriceId]' => '[specificPriceId]',
    ];
}
