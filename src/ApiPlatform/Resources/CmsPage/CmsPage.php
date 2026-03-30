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
            requirements: ['cmsPageId' => '\d+'],
            CQRSQuery: GetCmsPageForEditing::class,
            scopes: ['cms_page_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/cms-pages',
            CQRSCommand: AddCmsPageCommand::class,
            CQRSQuery: GetCmsPageForEditing::class,
            scopes: ['cms_page_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/cms-pages/{cmsPageId}',
            requirements: ['cmsPageId' => '\d+'],
            CQRSCommand: EditCmsPageCommand::class,
            CQRSQuery: GetCmsPageForEditing::class,
            scopes: ['cms_page_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/cms-pages/{cmsPageId}',
            requirements: ['cmsPageId' => '\d+'],
            CQRSCommand: DeleteCmsPageCommand::class,
            scopes: ['cms_page_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/cms-pages/{cmsPageId}/toggle-status',
            requirements: ['cmsPageId' => '\d+'],
            output: false,
            CQRSCommand: ToggleCmsPageStatusCommand::class,
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
    #[Assert\NotBlank]
    public array $titles;

    #[LocalizedValue]
    public array $metaTitles;

    #[LocalizedValue]
    public array $metaDescriptions;

    #[LocalizedValue]
    public array $friendlyUrls;

    #[LocalizedValue]
    public array $contents;

    public bool $indexedForSearch;

    public bool $displayed;

    /**
     * @var int[]
     */
    public array $shopIds;

    public string $previewUrl;

    public const QUERY_MAPPING = [
        '[localizedTitle]' => '[titles]',
        '[localizedMetaTitle]' => '[metaTitles]',
        '[localizedMetaDescription]' => '[metaDescriptions]',
        '[localizedFriendlyUrl]' => '[friendlyUrls]',
        '[localizedContent]' => '[contents]',
        '[isIndexedForSearch]' => '[indexedForSearch]',
        '[isDisplayed]' => '[displayed]',
        '[shopAssociation]' => '[shopIds]',
    ];

    public const COMMAND_MAPPING = [
        '[titles]' => '[localizedTitle]',
        '[metaTitles]' => '[localizedMetaTitle]',
        '[metaDescriptions]' => '[localizedMetaDescription]',
        '[friendlyUrls]' => '[localizedFriendlyUrl]',
        '[contents]' => '[localizedContent]',
        '[shopIds]' => '[shopAssociation]',
    ];
}
