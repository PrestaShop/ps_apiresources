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
use PrestaShop\PrestaShop\Core\Search\Filters\CmsPageCategoryFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/cms-pages/categories',
            scopes: [
                'cms_page_category_read',
            ],
            ApiResourceMapping: self::MAPPING,
            gridDataFactory: 'prestashop.core.grid.data_provider.cms_page_category',
            filtersClass: CmsPageCategoryFilters::class,
            filtersMapping: [
                '[cmsPageCategoryId]' => '[id_cms_category]',
                '[parentId]' => '[id_cms_category_parent]',
                '[displayed]' => '[active]',
            ],
            openapi: new OpenApiOperation(
                summary: 'List the CMS page categories.',
                description: 'Lists the CMS page categories associated with the shops of the context, the root '
                    . 'category included. Filters: cmsPageCategoryId, parentId, displayed and position match exactly, '
                    . 'name and description match partially; an unknown filter is ignored. The position filter '
                    . 'counts from 1 while the returned position counts from 0. Every field except parentId can be '
                    . 'used in orderBy. The texts are returned in the language of the context (langId parameter).',
            ),
        ),
    ],
    // parentId is null for the root category, like on GET /cms-pages/categories/{cmsPageCategoryId}
    normalizationContext: ['skip_null_values' => false],
)]
class CmsPageCategoryList
{
    #[ApiProperty(identifier: true)]
    public int $cmsPageCategoryId;

    public string $name;

    public string $description;

    public ?int $parentId;

    public bool $displayed;

    public int $position;

    public const MAPPING = [
        '[id_cms_category]' => '[cmsPageCategoryId]',
        '[id_parent]' => '[parentId]',
        '[active]' => '[displayed]',
    ];

    // The grid returns 0 as the parent of the root category
    public function setParentId(int $parentId): self
    {
        $this->parentId = 0 === $parentId ? null : $parentId;

        return $this;
    }
}
