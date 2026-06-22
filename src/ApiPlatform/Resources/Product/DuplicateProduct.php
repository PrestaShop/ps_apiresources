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
use PrestaShop\PrestaShop\Core\Domain\Product\Command\DuplicateProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Query\GetProductForEditing;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\ShopAssociationNotFound;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/products/{productId}/duplicate',
            requirements: ['productId' => '\d+'],
            // The product to duplicate is identified by the URI, no request body is expected
            allowEmptyBody: true,
            CQRSCommand: DuplicateProductCommand::class,
            CQRSQuery: GetProductForEditing::class,
            scopes: [
                'product_write',
            ],
            CQRSQueryMapping: Product::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ShopAssociationNotFound::class => Response::HTTP_NOT_FOUND,
    ],
)]
class DuplicateProduct extends Product
{
}
