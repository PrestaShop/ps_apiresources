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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Category;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Category\Command\DeleteCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/categories/{categoryId}/{mode}',
            CQRSCommand: DeleteCategoryCommand::class,
            scopes: ['category_write'],
            openapiContext: [
                'summary' => 'Delete a category using a specific mode',
                'parameters' => [
                    [
                        'name' => 'categoryId',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'integer'],
                        'description' => 'Category ID to delete',
                    ],
                    [
                        'name' => 'mode',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                            'enum' => ['associate_and_disable', 'associate_only', 'remove_associated'],
                        ],
                        'description' => 'Delete mode: "associate_and_disable" associate products with parent category and disable them, "associate_only" associate products with parent and do not change their status, "remove_associated" remove products that are associated only with category that is being deleted',
                    ],
                ],
            ],
            CQRSCommandMapping: [
                '[deleteMode]' => '[mode]',
            ],
        ),
    ],
    exceptionToStatus: [
        CategoryConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CategoryNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CategoryDelete
{
    #[ApiProperty(identifier: true)]
    public int $categoryId;

    public string $deleteMode;
}
