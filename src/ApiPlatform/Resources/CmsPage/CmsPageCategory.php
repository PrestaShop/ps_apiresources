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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
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
            uriTemplate: '/cms-pages/categories/{cmsPageCategoryId}',
            requirements: ['cmsPageCategoryId' => '\d+'],
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            scopes: [
                'cms_page_category_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/cms-pages/categories',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCmsPageCategoryCommand::class,
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            scopes: [
                'cms_page_category_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            extraProperties: [
                // The defaults of the back office form, applied before the validation from PrestaShop 9.2.0
                // (the fields stay required on older versions)
                'defaultValues' => self::CREATE_DEFAULT_VALUES,
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/cms-pages/categories/{cmsPageCategoryId}',
            requirements: ['cmsPageCategoryId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditCmsPageCategoryCommand::class,
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            scopes: [
                'cms_page_category_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            openapi: new OpenApiOperation(
                summary: 'Update a CMS page category.',
                description: 'Partial update: only the sent fields change. A localized field replaces all its '
                    . 'languages: send every language, a missing one takes the value of the default language for '
                    . 'names and linkRewrites, and becomes empty for the other localized fields.',
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/cms-pages/categories/{cmsPageCategoryId}/toggle-status',
            requirements: ['cmsPageCategoryId' => '\d+'],
            allowEmptyBody: true,
            CQRSCommand: ToggleCmsPageCategoryStatusCommand::class,
            // Replays the query of the GET, so the toggle answers with the category it flipped
            CQRSQuery: GetCmsPageCategoryForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: [
                'cms_page_category_write',
            ],
            openapi: new OpenApiOperation(
                summary: 'Toggle the status of a CMS page category.',
                description: 'Flips the displayed status without a body and returns the updated category. To set a '
                    . 'given status, send displayed to PATCH /cms-pages/categories/{cmsPageCategoryId}.',
            ),
        ),
        new CQRSDelete(
            uriTemplate: '/cms-pages/categories/{cmsPageCategoryId}',
            requirements: ['cmsPageCategoryId' => '\d+'],
            CQRSCommand: DeleteCmsPageCategoryCommand::class,
            scopes: [
                'cms_page_category_write',
            ],
            openapi: new OpenApiOperation(
                summary: 'Delete a CMS page category.',
                description: 'Deletes the category with all its subcategories and all their pages. The root '
                    . 'category cannot be deleted (422).',
            ),
        ),
    ],
    // parentId is null for the root category: returned as null, not omitted
    normalizationContext: ['skip_null_values' => false],
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

    #[ApiProperty(openapiContext: [
        'description' => 'Parent category, null for the root category. An unknown parent answers 404 from '
            . 'PrestaShop 9.2.1, older versions store it. Reading the root category needs PrestaShop 9.2.1.',
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Positive]
    public ?int $parentId;

    // The NotNull constraints of the Create group answer 422 on the cores older than 9.2.0, which ignore
    // CREATE_DEFAULT_VALUES: a missing displayed is a 400 there, a missing descriptions a TypeError in the handler
    #[Assert\NotNull(groups: ['Create'])]
    public bool $displayed;

    #[LocalizedValue]
    #[Assert\NotNull(groups: ['Create'])]
    public array $descriptions;

    #[LocalizedValue]
    public array $metaTitles;

    #[LocalizedValue]
    public array $metaDescriptions;

    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: [
        'description' => 'Shops of the category. Optional on creation from PrestaShop 9.2.0, the shops of the context '
            . 'are used when it is omitted (required on older versions). The existence of the shops is not checked.',
        'example' => [1, 3],
    ])]
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $shopIds;

    /**
     * The values of CmsPageCategoryFormDataProvider::getDefaultData(); descriptions is empty since
     * AddCmsPageCategoryHandler checks it whether it was set or not.
     */
    public const CREATE_DEFAULT_VALUES = [
        'displayed' => true,
        'descriptions' => [],
        'shopIds' => '[_context][shopIds]',
    ];

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

    // Up to PrestaShop 9.2.0 the query result may expose the status as a string ("1"/"0"): the core casts it from
    // 9.2.1 (QueryResultSerializerTrait), remove this setter once 9.2.1 is the minimum version
    public function setDisplayed(string|int|bool $displayed): self
    {
        $this->displayed = (bool) $displayed;

        return $this;
    }
}
