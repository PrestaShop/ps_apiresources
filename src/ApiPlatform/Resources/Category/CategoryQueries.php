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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Category;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Category\Command\UpdateCategoryPositionCommand;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Category\Query\GetCategoriesTree;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/categories/tree',
            openapiContext: ['summary' => 'Get categories tree', 'description' => 'Retrieves the full category tree for a given language and shop'],
            CQRSQuery: GetCategoriesTree::class,
            scopes: [
                'category_read',
            ],
            CQRSQueryMapping: [
                '[_context][langId]' => '[languageId]',
                '[_context][shopId]' => '[shopId]',
            ],
        ),
        new CQRSCommand(
            uriTemplate: '/categories/{categoryId}/position',
            method: 'PATCH',
            requirements: ['categoryId' => '\d+'],
            openapiContext: ['summary' => 'Update category position', 'description' => 'Updates the position of a category within its parent'],
            CQRSCommand: UpdateCategoryPositionCommand::class,
            scopes: [
                'category_write',
            ],
        ),
    ],
    exceptionToStatus: [
        CategoryNotFoundException::class => Response::HTTP_NOT_FOUND,
        CategoryConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CategoryQueries
{
    #[ApiProperty(identifier: true)]
    public int $categoryId;

    public int $parentCategoryId;

    public int $way;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'string',
        ],
        'example' => ['1_0', '2_1', '3_2'],
    ])]
    public array $positions;

    public bool $foundFirst;
}
