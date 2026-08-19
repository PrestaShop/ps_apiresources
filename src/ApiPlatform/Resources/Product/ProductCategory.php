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
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\AssignProductToCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\RemoveAllAssociatedProductCategoriesCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\SetAssociatedProductCategoriesCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            // Partially modifies the category associations (adds one), hence the PATCH method
            uriTemplate: '/products/{productId}/assign-to-categories',
            requirements: ['productId' => '\d+'],
            read: false,
            validationContext: ['groups' => ['Default', 'AssignCategory']],
            CQRSCommand: AssignProductToCategoryCommand::class,
            status: Response::HTTP_NO_CONTENT,
            output: false,
            scopes: [
                'product_write',
            ],
        ),
        new CQRSUpdate(
            // Completely replaces the category associations, hence the PUT method
            uriTemplate: '/products/{productId}/categories',
            requirements: ['productId' => '\d+'],
            read: false,
            validationContext: ['groups' => ['Default', 'SetCategories']],
            CQRSCommand: SetAssociatedProductCategoriesCommand::class,
            CQRSCommandMapping: ProductCategory::COMMAND_MAPPING,
            status: Response::HTTP_NO_CONTENT,
            output: false,
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
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CategoryConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductCategory
{
    #[Assert\NotBlank(groups: ['AssignCategory'])]
    #[Assert\Positive(groups: ['AssignCategory'])]
    public int $categoryId;

    public int $productId;

    #[Assert\NotBlank(groups: ['SetCategories'])]
    #[Assert\Positive(groups: ['SetCategories'])]
    public ?int $defaultCategoryId;

    #[Assert\NotBlank(groups: ['SetCategories'])]
    #[Assert\All([new Assert\Positive()], groups: ['SetCategories'])]
    public ?array $categoryIds = null;

    public const COMMAND_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
    ];
}
