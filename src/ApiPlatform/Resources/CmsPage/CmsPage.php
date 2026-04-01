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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CmsPage;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\CleanHtml;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\IsUrlRewrite;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Command\AddCmsPageCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Command\DeleteCmsPageCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Command\EditCmsPageCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Command\ToggleCmsPageStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Exception\CannotAddCmsPageException;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Exception\CannotDeleteCmsPageException;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Exception\CannotEditCmsPageException;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Exception\CannotToggleCmsPageException;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Exception\CmsPageNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Query\GetCmsPageForEditing;
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
            uriTemplate: '/cms-pages/{cmsPageId}',
            CQRSQuery: GetCmsPageForEditing::class,
            scopes: ['cms_page_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/cms-pages',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCmsPageCommand::class,
            CQRSQuery: GetCmsPageForEditing::class,
            scopes: ['cms_page_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/cms-pages/{cmsPageId}',
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditCmsPageCommand::class,
            CQRSQuery: GetCmsPageForEditing::class,
            scopes: ['cms_page_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
        ),
        new CQRSUpdate(
            uriTemplate: '/cms-pages/{cmsPageId}/toggle-status',
            output: false,
            allowEmptyBody: true,
            CQRSCommand: ToggleCmsPageStatusCommand::class,
            scopes: ['cms_page_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/cms-pages/{cmsPageId}',
            CQRSCommand: DeleteCmsPageCommand::class,
            scopes: ['cms_page_write'],
        ),
    ],
    exceptionToStatus: [
        CmsPageNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotAddCmsPageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotEditCmsPageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteCmsPageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotToggleCmsPageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CmsPage
{
    #[ApiProperty(identifier: true)]
    public int $cmsPageId;

    public int $cmsPageCategoryId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'titles')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'titles', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_GENERIC_NAME]),
        new Assert\Length(['max' => 255]),
    ])]
    public array $titles;

    #[LocalizedValue]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_GENERIC_NAME]),
        new Assert\Length(['max' => 255]),
    ])]
    public array $metaTitles;

    #[LocalizedValue]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_GENERIC_NAME]),
        new Assert\Length(['max' => 512]),
    ])]
    public array $metaDescriptions;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'friendlyUrls')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'friendlyUrls', allowNull: true)]
    #[Assert\All(constraints: [
        new IsUrlRewrite(),
        new Assert\Length(['max' => 128]),
    ])]
    public array $friendlyUrls;

    #[LocalizedValue]
    #[Assert\All(constraints: [
        new CleanHtml(),
    ])]
    public array $contents;

    public bool $indexedForSearch;

    public bool $enabled;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank(allowNull: true)]
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[localizedTitle]' => '[titles]',
        '[localizedMetaTitle]' => '[metaTitles]',
        '[localizedMetaDescription]' => '[metaDescriptions]',
        '[localizedFriendlyUrl]' => '[friendlyUrls]',
        '[localizedContent]' => '[contents]',
        '[indexedForSearch]' => '[indexedForSearch]',
        '[displayed]' => '[enabled]',
        '[shopAssociation]' => '[shopIds]',
    ];

    public const CREATE_COMMAND_MAPPING = [
        '[titles]' => '[localizedTitle]',
        '[metaTitles]' => '[localizedMetaTitle]',
        '[metaDescriptions]' => '[localizedMetaDescription]',
        '[friendlyUrls]' => '[localizedFriendlyUrl]',
        '[contents]' => '[localizedContent]',
        '[enabled]' => '[displayed]',
        '[shopIds]' => '[shopAssociation]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        '[titles]' => '[localizedTitle]',
        '[metaTitles]' => '[localizedMetaTitle]',
        '[metaDescriptions]' => '[localizedMetaDescription]',
        '[friendlyUrls]' => '[localizedFriendlyUrl]',
        '[contents]' => '[localizedContent]',
        '[indexedForSearch]' => '[isIndexedForSearch]',
        '[enabled]' => '[isDisplayed]',
        '[shopIds]' => '[shopAssociation]',
    ];
}
