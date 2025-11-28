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
use PrestaShop\Module\APIResources\Validation\IframeValidationGroupsResolver;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Category\Command\AddCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\Category\Command\DeleteCategoryCoverImageCommand;
use PrestaShop\PrestaShop\Core\Domain\Category\Command\DeleteCategoryThumbnailImageCommand;
use PrestaShop\PrestaShop\Core\Domain\Category\Command\EditCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\Category\Command\SetCategoryIsEnabledCommand;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Category\Query\GetCategoryForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/categories/{categoryId}',
            CQRSQuery: GetCategoryForEditing::class,
            scopes: [
                'category_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/categories',
            validationContext: [IframeValidationGroupsResolver::class, 'create'],
            CQRSCommand: AddCategoryCommand::class,
            CQRSQuery: GetCategoryForEditing::class,
            scopes: [
                'category_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/categories/{categoryId}',
            validationContext: [IframeValidationGroupsResolver::class, 'update'],
            CQRSCommand: EditCategoryCommand::class,
            CQRSQuery: GetCategoryForEditing::class,
            scopes: [
                'category_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/categories/{categoryId}/status',
            CQRSCommand: SetCategoryIsEnabledCommand::class,
            CQRSQuery: GetCategoryForEditing::class,
            scopes: [
                'category_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[enabled]' => '[isEnabled]',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/categories/{categoryId}/cover',
            CQRSCommand: DeleteCategoryCoverImageCommand::class,
            scopes: [
                'category_write',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/categories/{categoryId}/thumbnail',
            CQRSCommand: DeleteCategoryThumbnailImageCommand::class,
            scopes: [
                'category_write',
            ],
        ),
    ],
    exceptionToStatus: [
        CategoryConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CategoryNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Category
{
    #[ApiProperty(identifier: true)]
    public int $categoryId;

    public bool $enabled;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_CATALOG_NAME,
        ]),
    ])]
    public array $names;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'descriptions')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'descriptions', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::CLEAN_HTML_NO_IFRAME,
            'groups' => ['NoIframe'],
        ]),
        new TypedRegex([
            'type' => TypedRegex::CLEAN_HTML_ALLOW_IFRAME,
            'groups' => ['AllowIframe'],
        ]),
    ])]
    public array $descriptions;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'additionalDescriptions')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'additionalDescriptions', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::CLEAN_HTML_NO_IFRAME,
            'groups' => ['NoIframe'],
        ]),
        new TypedRegex([
            'type' => TypedRegex::CLEAN_HTML_NO_IFRAME,
            'groups' => ['AllowIframe'],
        ]),
    ])]
    public array $additionalDescriptions;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'linkRewrites')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'linkRewrites', allowNull: false)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_URL,
        ]),
    ])]
    public array $linkRewrites;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'metaTitles')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'metaTitles', allowNull: true)]
    public array $metaTitles;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'metaDescriptions')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'metaDescriptions', allowNull: true)]
    public array $metaDescriptions;

    public int $position;

    public int $parentId;

    public string $redirectType;

    public ?int $redirectTarget = null;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank(allowNull: true)]
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[id]' => '[categoryId]',
        '[active]' => '[enabled]',
        '[name]' => '[names]',
        '[description]' => '[descriptions]',
        '[additionalDescription]' => '[additionalDescriptions]',
        '[associatedShopIds]' => '[shopIds]',
        '[metaTitle]' => '[metaTitles]',
        '[metaDescription]' => '[metaDescriptions]',
        '[linkRewrite]' => '[linkRewrites]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[enabled]' => '[isEnabled]',
        '[descriptions]' => '[localizedDescriptions]',
        '[additionalDescriptions]' => '[localizedAdditionalDescriptions]',
        '[shopIds]' => '[associatedShopIds]',
        '[metaTitles]' => '[localizedMetaTitles]',
        '[metaDescriptions]' => '[localizedMetaDescriptions]',
        '[linkRewrites]' => '[localizedLinkRewrites]',
    ];
}
