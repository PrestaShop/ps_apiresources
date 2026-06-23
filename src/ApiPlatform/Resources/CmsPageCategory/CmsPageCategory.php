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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CmsPageCategory;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
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

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/cms-page-categories/{cmsPageCategoryId}',
            requirements: ['cmsPageCategoryId' => '\d+'],
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            scopes: [
                'cms_page_category_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/cms-page-categories',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCmsPageCategoryCommand::class,
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            scopes: [
                'cms_page_category_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/cms-page-categories/{cmsPageCategoryId}',
            requirements: ['cmsPageCategoryId' => '\d+'],
            CQRSCommand: EditCmsPageCategoryCommand::class,
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            scopes: [
                'cms_page_category_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSUpdate(
            uriTemplate: '/cms-page-categories/{cmsPageCategoryId}/toggle-status',
            requirements: ['cmsPageCategoryId' => '\d+'],
            output: false,
            allowEmptyBody: true,
            CQRSCommand: ToggleCmsPageCategoryStatusCommand::class,
            scopes: [
                'cms_page_category_write',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/cms-page-categories/{cmsPageCategoryId}',
            requirements: ['cmsPageCategoryId' => '\d+'],
            output: false,
            CQRSCommand: DeleteCmsPageCategoryCommand::class,
            scopes: [
                'cms_page_category_write',
            ],
        ),
    ],
    exceptionToStatus: [
        CmsPageCategoryNotFoundException::class => Response::HTTP_NOT_FOUND,
        CmsPageCategoryConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CmsPageCategory
{
    #[ApiProperty(identifier: true)]
    public int $cmsPageCategoryId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    public array $names;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'linkRewrites')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'linkRewrites', allowNull: true)]
    public array $linkRewrites;

    public int $parentId;

    public bool $displayed;

    #[LocalizedValue]
    public array $descriptions;

    #[LocalizedValue]
    public array $metaTitles;

    #[LocalizedValue]
    public array $metaDescriptions;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[localisedName]' => '[names]',
        '[localisedFriendlyUrl]' => '[linkRewrites]',
        '[localisedDescription]' => '[descriptions]',
        '[metaTitle]' => '[metaTitles]',
        '[localisedMetaDescription]' => '[metaDescriptions]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localisedName]',
        '[linkRewrites]' => '[localisedFriendlyUrl]',
        '[displayed]' => '[isDisplayed]',
        '[descriptions]' => '[localisedDescription]',
        '[metaTitles]' => '[localisedMetaTitle]',
        '[metaDescriptions]' => '[localisedMetaDescription]',
        '[shopIds]' => '[shopAssociation]',
    ];
}
