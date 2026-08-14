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
use PrestaShop\PrestaShop\Core\Domain\Carrier\Query\GetCarriersForProduct;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\SetCarriersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/carriers',
            requirements: ['productId' => '\d+'],
            CQRSQuery: GetCarriersForProduct::class,
            scopes: ['carrier_read'],
            // The GetCarriersForProduct query only exists since PrestaShop 9.2.0
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/carriers',
            requirements: ['productId' => '\d+'],
            CQRSCommand: SetCarriersCommand::class,
            CQRSQuery: GetCarriersForProduct::class,
            scopes: ['product_write'],
            // The response is built with GetCarriersForProduct, which only exists since PrestaShop 9.2.0
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductCarrier
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    /**
     * Carrier reference ids to associate with the product. Write-only: the responses expose the
     * resulting associations through the carriers property.
     */
    #[Assert\NotNull]
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 2]])]
    public ?array $carrierReferenceIds = null;

    /**
     * Read-only: carriers currently associated with the product.
     */
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'carrierId' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                ],
            ],
        ]
    )]
    public ?array $carriers = null;

    public const QUERY_MAPPING = [
        '[@index][id]' => '[carriers][@index][carrierId]',
        '[@index][name]' => '[carriers][@index][name]',
    ];

    public const COMMAND_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
    ];
}
