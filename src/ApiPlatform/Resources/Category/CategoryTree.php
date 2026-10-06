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
use PrestaShop\PrestaShop\Core\Domain\Category\Query\GetCategoriesTree;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/categories/trees',
            CQRSQuery: GetCategoriesTree::class,
            scopes: [
                'category_read',
            ],
            CQRSQueryMapping: [
                '[_context][langId]' => '[languageId]',
                '[_context][shopId]' => '[shopId]',
            ],
        ),
    ],
)]
class CategoryTree
{
    #[ApiProperty(identifier: true)]
    public int $categoryId;

    public bool $enabled;

    public string $name;

    public string $displayName;

    /**
     * Nested children categories, each with the same shape as this resource.
     */
    public array $children;

    // CategoryForTree exposes isActive(), normalized as "active" at every level of the tree. An
    // ApiResourceMapping only renames the first level, so the two setters rename it on each node.
    public function setActive(bool $active): self
    {
        $this->enabled = $active;

        return $this;
    }

    public function setChildren(array $children): self
    {
        $this->children = self::normalizeNodes($children);

        return $this;
    }

    private static function normalizeNodes(array $nodes): array
    {
        return array_map(static fn (array $node): array => [
            'categoryId' => $node['categoryId'],
            'enabled' => $node['active'],
            'name' => $node['name'],
            'displayName' => $node['displayName'],
            'children' => self::normalizeNodes($node['children'] ?? []),
        ], $nodes);
    }
}
