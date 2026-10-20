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
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Command\AddCmsPageCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Command\DeleteCmsPageCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Command\EditCmsPageCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Command\ToggleCmsPageCategoryStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Exception\CmsPageCategoryException;
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
            extraProperties: [
                // The shops of the context are used only when the payload has no shopIds (PrestaShop 9.2.0 and later,
                // shopIds is required on older versions)
                'defaultValues' => ['shopIds' => '[_context][shopIds]'],
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/cms-page-categories/{cmsPageCategoryId}',
            requirements: ['cmsPageCategoryId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
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
            allowEmptyBody: true,
            CQRSCommand: ToggleCmsPageCategoryStatusCommand::class,
            // Replays the query of the GET, so the toggle answers with the category it flipped
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
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
        // The handlers and CmsPageCategoryId throw the base exception, and the Cannot* exceptions extend it
        CmsPageCategoryException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
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

    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
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

    // The legacy query result may expose the status as a string ("1"/"0"), so we
    // coerce it here to keep the typed bool property happy across versions.
    public function setDisplayed(string|int|bool $displayed): self
    {
        $this->displayed = (bool) $displayed;

        return $this;
    }
}
