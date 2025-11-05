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
use PrestaShop\PrestaShop\Core\Domain\Product\Command\AssignProductToCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\RemoveAllAssociatedProductCategoriesCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\SetAssociatedProductCategoriesCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/product/category',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AssignProductToCategoryCommand::class,
            status: Response::HTTP_CREATED,
            scopes: [
                'product_write',
            ],
        ),
        new CQRSCreate(
            uriTemplate: '/product/categories',
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: SetAssociatedProductCategoriesCommand::class,
            status: Response::HTTP_CREATED,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/product/category/{productId}',
            CQRSCommand: RemoveAllAssociatedProductCategoriesCommand::class,
            status: Response::HTTP_NO_CONTENT,
            output: false,
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
            scopes: [
                'product_write',
            ],
        ),
    ],
)]
class ProductCategory
{
    public int $categoryId;

    public int $productId;

    public ?int $defaultCategoryId;
}
