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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CmsPageCategory;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Command\AddCmsPageCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Command\DeleteCmsPageCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Command\EditCmsPageCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Command\ToggleCmsPageCategoryStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Exception\CmsPageCategoryConstraintException;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Exception\CmsPageCategoryNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Query\GetCmsPageCategoryForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/cms-page-categories/{cmsPageCategoryId}',
            requirements: ['cmsPageCategoryId' => '\d+'],
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            scopes: ['cms_page_category_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/cms-page-categories',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCmsPageCategoryCommand::class,
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            scopes: ['cms_page_category_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/cms-page-categories/{cmsPageCategoryId}',
            requirements: ['cmsPageCategoryId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditCmsPageCategoryCommand::class,
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            scopes: ['cms_page_category_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSUpdate(
            uriTemplate: '/cms-page-categories/{cmsPageCategoryId}/toggle-status',
            requirements: ['cmsPageCategoryId' => '\d+'],
            CQRSCommand: ToggleCmsPageCategoryStatusCommand::class,
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            scopes: ['cms_page_category_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            allowEmptyBody: true,
        ),
        new CQRSDelete(
            uriTemplate: '/cms-page-categories/{cmsPageCategoryId}',
            requirements: ['cmsPageCategoryId' => '\d+'],
            CQRSCommand: DeleteCmsPageCategoryCommand::class,
            scopes: ['cms_page_category_write'],
        ),
    ],
    exceptionToStatus: [
        CmsPageCategoryConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CmsPageCategoryNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CmsPageCategory
{
    #[ApiProperty(identifier: true)]
    public int $cmsPageCategoryId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_GENERIC_NAME]),
    ])]
    public array $names;

    public bool $enabled;

    public int $parentId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'friendlyUrls')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'friendlyUrls', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_LINK_REWRITE]),
    ])]
    public array $friendlyUrls;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'descriptions', allowNull: true)]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'descriptions', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_GENERIC_NAME]),
    ])]
    public ?array $descriptions = null;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'metaTitles', allowNull: true)]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'metaTitles', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_GENERIC_NAME]),
    ])]
    public ?array $metaTitles = null;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'metaDescriptions', allowNull: true)]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'metaDescriptions', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_GENERIC_NAME]),
    ])]
    public ?array $metaDescriptions = null;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank(allowNull: true)]
    public ?array $shopIds = null;

    public const QUERY_MAPPING = [
        '[localisedName]' => '[names]',
        '[displayed]' => '[enabled]',
        '[parentId][cmsPageCategoryId]' => '[parentId]',
        '[localisedDescription]' => '[descriptions]',
        '[metaTitle]' => '[metaTitles]',
        '[localisedMetaDescription]' => '[metaDescriptions]',
        '[localisedFriendlyUrl]' => '[friendlyUrls]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localisedName]',
        '[friendlyUrls]' => '[localisedFriendlyUrl]',
        '[enabled]' => '[isDisplayed]',
        '[descriptions]' => '[localisedDescription]',
        '[metaTitles]' => '[localisedMetaTitle]',
        '[metaDescriptions]' => '[localisedMetaDescription]',
        '[shopIds]' => '[shopAssociation]',
    ];
}
