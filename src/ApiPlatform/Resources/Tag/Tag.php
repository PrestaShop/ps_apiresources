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
 * @author    Pascal Cescon <pascal.cescon@gmail.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Tag;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Tag\Command\AddTagCommand;
use PrestaShop\PrestaShop\Core\Domain\Tag\Command\DeleteTagCommand;
use PrestaShop\PrestaShop\Core\Domain\Tag\Command\EditTagCommand;
use PrestaShop\PrestaShop\Core\Domain\Tag\Exception\CannotAddTagException;
use PrestaShop\PrestaShop\Core\Domain\Tag\Exception\CannotUpdateTagException;
use PrestaShop\PrestaShop\Core\Domain\Tag\Exception\DuplicateTagException;
use PrestaShop\PrestaShop\Core\Domain\Tag\Exception\TagConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Tag\Exception\TagException;
use PrestaShop\PrestaShop\Core\Domain\Tag\Exception\TagNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Tag\Query\GetTagForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/tags/{tagId}',
            requirements: ['tagId' => '\d+'],
            CQRSQuery: GetTagForEditing::class,
            scopes: ['tag_read'],
        ),
        new CQRSCreate(
            uriTemplate: '/tags',
            CQRSCommand: AddTagCommand::class,
            CQRSQuery: GetTagForEditing::class,
            scopes: ['tag_write'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/tags/{tagId}',
            requirements: ['tagId' => '\d+'],
            CQRSCommand: EditTagCommand::class,
            CQRSQuery: GetTagForEditing::class,
            scopes: ['tag_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/tags/{tagId}',
            requirements: ['tagId' => '\d+'],
            CQRSCommand: DeleteTagCommand::class,
            scopes: ['tag_write'],
        ),
    ],
    exceptionToStatus: [
        TagNotFoundException::class => Response::HTTP_NOT_FOUND,
        TagException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        TagConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotAddTagException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateTagException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        DuplicateTagException::class => Response::HTTP_CONFLICT,
    ],
)]
class Tag
{
    #[ApiProperty(identifier: true)]
    public int $tagId;

    #[Assert\NotBlank]
    public string $name;

    #[Assert\NotBlank]
    public int $languageId;

    /**
     * @var int[]
     */
    public array $productIds = [];
}
