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
use PrestaShop\PrestaShop\Core\Domain\Category\Command\UpdateCategoryPositionCommand;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryException;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/categories/{categoryId}/positions',
            requirements: ['categoryId' => '\d+'],
            read: false,
            output: false,
            CQRSCommand: UpdateCategoryPositionCommand::class,
            scopes: [
                'category_write',
            ],
        ),
    ],
    exceptionToStatus: [
        CategoryNotFoundException::class => Response::HTTP_NOT_FOUND,
        CategoryException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CategoryPosition
{
    #[ApiProperty(identifier: true)]
    public int $categoryId;

    public int $parentCategoryId;

    /**
     * Direction of the move: 0 to move up, 1 to move down.
     */
    public int $way;

    /**
     * The full ordered list of the parent's children, each entry formatted as
     * "{rowId}_{parentCategoryId}_{categoryId}". Only the two trailing segments are read by the
     * handler; the leading row id (e.g. "tr_", "0_") is a legacy grid artifact and is ignored,
     * so any prefix may be passed.
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => ['type' => 'string'],
        'example' => ['0_2_5', '0_2_6', '0_2_7'],
    ])]
    #[Assert\NotBlank]
    public array $positions;

    public bool $foundFirst;
}
