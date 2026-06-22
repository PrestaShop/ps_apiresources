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

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\RemoveAllProductTagsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\SetProductTagsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/tags',
            requirements: ['productId' => '\d+'],
            CQRSCommand: SetProductTagsCommand::class,
            // No output 204 code
            output: false,
            status: Response::HTTP_NO_CONTENT,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[tags]' => '[localizedTags]',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}/tags',
            requirements: ['productId' => '\d+'],
            CQRSCommand: RemoveAllProductTagsCommand::class,
            output: false,
            status: Response::HTTP_NO_CONTENT,
            scopes: [
                'product_write',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductTags
{
    public int $productId;

    /**
     * Localized list of tags, keyed by locale, each value being an array of tags.
     */
    #[LocalizedValue]
    public array $tags;
}
