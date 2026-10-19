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
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\InvalidProductTypeException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Command\RemoveAllProductsFromPackCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Command\SetPackProductsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Exception\ProductPackException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/pack-products',
            requirements: ['productId' => '\d+'],
            output: false,
            CQRSCommand: SetPackProductsCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            scopes: ['product_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}/pack-products',
            requirements: ['productId' => '\d+'],
            output: false,
            CQRSCommand: RemoveAllProductsFromPackCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            scopes: ['product_write'],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidProductTypeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ProductPackException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductPackProducts
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    /**
     * The products contained in the pack.
     *
     * @var array<int, array{product_id: int, quantity: int, combination_id?: int}>
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => ['type' => 'object'],
        'example' => [['product_id' => 1, 'quantity' => 2]],
    ])]
    public array $products;

    /**
     * The pack commands expect a $packId constructor argument built from the product id.
     */
    public const COMMAND_MAPPING = [
        '[productId]' => '[packId]',
    ];
}
