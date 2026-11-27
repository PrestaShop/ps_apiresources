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
use ApiPlatform\OpenApi\Model\RequestBody;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Command\AddCmsPageCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Command\DeleteCmsPageCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Command\EditCmsPageCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Command\ToggleCmsPageStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Exception\CmsPageException;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Exception\CmsPageNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\CmsPage\Query\GetCmsPageForEditing;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Exception\CmsPageCategoryException;
use PrestaShop\PrestaShop\Core\Domain\CmsPageCategory\Exception\CmsPageCategoryNotFoundException;
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
            scopes: [
                'cms_page_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/cms-pages',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCmsPageCommand::class,
            CQRSQuery: GetCmsPageForEditing::class,
            scopes: [
                'cms_page_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
            openapi: new OpenApiOperation(
                // Written by hand: AddCmsPageCommand exposes getCmsPageCategory() for its $cmsPageCategoryId
                // parameter, so the body generated from the command misses that required field
                requestBody: new RequestBody(
                    'The new CmsPage resource',
                    new \ArrayObject([
                        'application/json' => ['schema' => self::CREATE_REQUEST_SCHEMA],
                    ]),
                    true,
                ),
            ),
            extraProperties: [
                // The defaults of the back office form, applied before the validation from PrestaShop 9.2.0
                // (the fields stay required on older versions)
                'defaultValues' => self::CREATE_DEFAULT_VALUES,
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/cms-pages/{cmsPageId}',
            requirements: ['cmsPageId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditCmsPageCommand::class,
            CQRSQuery: GetCmsPageForEditing::class,
            scopes: [
                'cms_page_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            openapi: new OpenApiOperation(
                summary: 'Update a CMS page.',
                description: 'Partial update: only the sent fields change. A localized field replaces all its '
                    . 'languages: send every language, a missing one takes the value of the default language for '
                    . 'titles and linkRewrites, and becomes empty for the other localized fields.',
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/cms-pages/{cmsPageId}/toggle-status',
            requirements: ['cmsPageId' => '\d+'],
            allowEmptyBody: true,
            CQRSCommand: ToggleCmsPageStatusCommand::class,
            // Replays the query of the GET, so the toggle answers with the page it flipped
            CQRSQuery: GetCmsPageForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: [
                'cms_page_write',
            ],
            openapi: new OpenApiOperation(
                summary: 'Toggle the status of a CMS page.',
                description: 'Flips the displayed status without a body and returns the updated page. To set a '
                    . 'given status, send displayed to PATCH /cms-pages/{cmsPageId}.',
            ),
        ),
        new CQRSDelete(
            uriTemplate: '/cms-pages/{cmsPageId}',
            requirements: ['cmsPageId' => '\d+'],
            CQRSCommand: DeleteCmsPageCommand::class,
            scopes: [
                'cms_page_write',
            ],
        ),
    ],
    exceptionToStatus: [
        CmsPageNotFoundException::class => Response::HTTP_NOT_FOUND,
        // The CmsPage domain has no constraint exception — unlike CmsPageCategory — so the
        // base exception carries the CannotAdd/CannotEdit/CannotDelete/CannotToggle cases.
        // CmsPageNotFoundException is declared above and wins, being more specific.
        CmsPageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // The page handlers check the category of the payload and throw the exceptions of its own domain
        CmsPageCategoryNotFoundException::class => Response::HTTP_NOT_FOUND,
        CmsPageCategoryException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CmsPage
{
    #[ApiProperty(identifier: true)]
    public int $cmsPageId;

    // The constraints give a 422 instead of the 400 of a missing constructor argument
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Positive]
    public int $cmsPageCategoryId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'titles')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'titles', allowNull: true)]
    public array $titles;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'linkRewrites')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'linkRewrites', allowNull: true)]
    public array $linkRewrites;

    // The NotNull constraints of the Create group answer 422 instead of the 400 of a missing constructor argument
    // on the cores older than 9.2.0, which ignore CREATE_DEFAULT_VALUES
    #[LocalizedValue]
    #[Assert\NotNull(groups: ['Create'])]
    public array $metaTitles;

    #[LocalizedValue]
    #[Assert\NotNull(groups: ['Create'])]
    public array $metaDescriptions;

    #[LocalizedValue]
    #[Assert\NotNull(groups: ['Create'])]
    public array $contents;

    // The schema keeps the boolean type that the compatibility setters below would widen to their union
    #[ApiProperty(schema: ['type' => 'boolean'])]
    #[Assert\NotNull(groups: ['Create'])]
    public bool $indexedForSearch;

    #[ApiProperty(schema: ['type' => 'boolean'])]
    #[Assert\NotNull(groups: ['Create'])]
    public bool $displayed;

    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: [
        'description' => self::SHOP_IDS_DESCRIPTION,
        'example' => [1, 3],
    ])]
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $shopIds;

    /**
     * The values of CmsPageFormDataProvider::getDefaultData(), empty for the localized fields the form does not
     * require.
     */
    public const CREATE_DEFAULT_VALUES = [
        'metaTitles' => [],
        'metaDescriptions' => [],
        'contents' => [],
        'indexedForSearch' => true,
        'displayed' => false,
        'shopIds' => '[_context][shopIds]',
    ];

    private const SHOP_IDS_DESCRIPTION = 'Shops of the page. Optional on creation from PrestaShop 9.2.0, the shops of '
        . 'the context are used when it is omitted (required on older versions). The existence of the shops is not '
        . 'checked.';

    private const LOCALIZED_SCHEMA = [
        'type' => 'object',
        'example' => ['en-US' => 'value', 'fr-FR' => 'valeur'],
    ];

    // The body generated from AddCmsPageCommand, plus the cmsPageCategoryId it misses
    public const CREATE_REQUEST_SCHEMA = [
        'type' => 'object',
        'required' => ['cmsPageCategoryId', 'titles', 'linkRewrites'],
        'properties' => [
            'cmsPageCategoryId' => ['type' => 'integer', 'example' => 1],
            'titles' => self::LOCALIZED_SCHEMA,
            'linkRewrites' => self::LOCALIZED_SCHEMA,
            'metaTitles' => self::LOCALIZED_SCHEMA + ['default' => []],
            'metaDescriptions' => self::LOCALIZED_SCHEMA + ['default' => []],
            'contents' => self::LOCALIZED_SCHEMA + ['default' => []],
            'indexedForSearch' => ['type' => 'boolean', 'default' => true],
            'displayed' => ['type' => 'boolean', 'default' => false],
            'shopIds' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'description' => self::SHOP_IDS_DESCRIPTION,
                'example' => [1, 3],
            ],
        ],
    ];

    // GetCmsPageForEditing also returns previewUrl, deliberately not exposed: no Admin API use case is known for it
    public const QUERY_MAPPING = [
        '[localizedTitle]' => '[titles]',
        '[localizedMetaTitle]' => '[metaTitles]',
        '[localizedMetaDescription]' => '[metaDescriptions]',
        '[localizedFriendlyUrl]' => '[linkRewrites]',
        '[localizedContent]' => '[contents]',
        '[shopAssociation]' => '[shopIds]',
    ];

    public const CREATE_COMMAND_MAPPING = [
        '[titles]' => '[localizedTitle]',
        '[metaTitles]' => '[localizedMetaTitle]',
        '[metaDescriptions]' => '[localizedMetaDescription]',
        '[linkRewrites]' => '[localizedFriendlyUrl]',
        '[contents]' => '[localizedContent]',
        '[shopIds]' => '[shopAssociation]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        '[titles]' => '[localizedTitle]',
        '[metaTitles]' => '[localizedMetaTitle]',
        '[metaDescriptions]' => '[localizedMetaDescription]',
        '[linkRewrites]' => '[localizedFriendlyUrl]',
        '[contents]' => '[localizedContent]',
        '[shopIds]' => '[shopAssociation]',
        '[indexedForSearch]' => '[isIndexedForSearch]',
        '[displayed]' => '[isDisplayed]',
    ];

    // Up to PrestaShop 9.2.1 the query result exposes the booleans as strings ("1"/"0"): the core casts them from
    // 9.2.2 (QueryResultSerializerTrait), remove these setters once 9.2.2 is the minimum version
    public function setIndexedForSearch(string|int|bool $indexedForSearch): self
    {
        $this->indexedForSearch = (bool) $indexedForSearch;

        return $this;
    }

    public function setDisplayed(string|int|bool $displayed): self
    {
        $this->displayed = (bool) $displayed;

        return $this;
    }
}
