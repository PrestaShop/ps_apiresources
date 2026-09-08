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
use PrestaShop\PrestaShop\Core\Domain\Product\Command\UpdateProductsPositionsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/products/positions',
            CQRSCommand: UpdateProductsPositionsCommand::class,
            // No output 204 code
            output: false,
            status: Response::HTTP_NO_CONTENT,
            scopes: [
                'product_write',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductException::class => Response::HTTP_INTERNAL_SERVER_ERROR,
    ],
)]
class ProductPositions
{
    /**
     * Id of the category the products are reordered in.
     */
    public int $categoryId;

    /**
     * Ordered list of position changes. Each entry holds the product id (rowId),
     * its current position (oldPosition) and its target position (newPosition).
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'rowId' => ['type' => 'integer'],
                'oldPosition' => ['type' => 'integer'],
                'newPosition' => ['type' => 'integer'],
            ],
        ],
        'example' => [
            ['rowId' => 1, 'oldPosition' => 0, 'newPosition' => 1],
            ['rowId' => 2, 'oldPosition' => 1, 'newPosition' => 0],
        ],
    ])]
    #[Assert\NotBlank]
    public array $positions;
}
