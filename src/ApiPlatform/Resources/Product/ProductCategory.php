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
use PrestaShop\PrestaShop\Core\Domain\Product\Query\GetProductForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/products/{productId}/assign-to-category',
            requirements: ['productId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AssignProductToCategoryCommand::class,
            CQRSQueryMapping: ProductCategory::QUERY_MAPPING,
            CQRSQuery: GetProductForEditing::class,
            scopes: [
                'product_write',
            ],
        ),
        new CQRSCreate(
            uriTemplate: '/products/{productId}/categories',
            requirements: ['productId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: SetAssociatedProductCategoriesCommand::class,
            CQRSCommandMapping: ProductCategory::COMMAND_MAPPING,
            CQRSQueryMapping: ProductCategory::QUERY_MAPPING,
            CQRSQuery: GetProductForEditing::class,
            scopes: [
                'product_write',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}/categories',
            requirements: ['productId' => '\d+'],
            CQRSCommand: RemoveAllAssociatedProductCategoriesCommand::class,
            CQRSCommandMapping: ProductCategory::COMMAND_MAPPING,
            status: Response::HTTP_NO_CONTENT,
            output: false,
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

    public ?array $categories;

    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[_context][langId]' => '[displayLanguageId]',
        // Enables the ProductCategoryOutputNormalizer
        // for serializing the response of CQRS category endpoints.
        'use_product_category_normalizer' => true,
    ];

    public const COMMAND_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
    ];
}
