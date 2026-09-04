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
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryException;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/categories/update-positions',
            output: false,
            CQRSCommand: UpdateCategoryPositionCommand::class,
            openapiContext: [
                'summary' => 'Update the position of a category within its parent',
                'description' => 'Reorders a category inside its parent category. The "positions" array mirrors the payload emitted by the admin drag-and-drop grid: each value is a token in the form "prefix_{parentCategoryId}_{categoryId}", and its numeric key indicates the desired position.',
            ],
            scopes: [
                'category_write',
            ],
        ),
    ],
    exceptionToStatus: [
        CategoryException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CategoryNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class UpdateCategoryPosition
{
    #[Assert\NotNull]
    public int $categoryId;

    #[Assert\NotNull]
    public int $parentCategoryId;

    #[Assert\NotNull]
    public int $way;

    /**
     * @var string[]
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => ['type' => 'string'],
        'example' => ['tr_2_3', 'tr_2_5', 'tr_2_7'],
    ])]
    #[Assert\NotBlank]
    public array $positions;

    #[Assert\NotNull]
    public bool $foundFirst;
}
