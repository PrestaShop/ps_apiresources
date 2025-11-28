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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\SearchCombinationsForAssociation;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/combinations/associations/search',
            CQRSQuery: SearchCombinationsForAssociation::class,
            scopes: [
                'product_read',
            ],
            exceptionToStatus: [
                ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            parameters: [
                new QueryParameter(
                    key: 'phrase',
                    required: true,
                    description: 'Search phrase (min 3 chars) to find combinations for association'
                ),
                new QueryParameter(
                    key: 'languageId',
                    schema: ['type' => 'integer'],
                    required: false,
                    description: 'Override language ID used for search'
                ),
                new QueryParameter(
                    key: 'shopId',
                    schema: ['type' => 'integer'],
                    required: false,
                    description: 'Override shop ID used for search'
                ),
                new QueryParameter(
                    key: 'limit',
                    schema: ['type' => 'integer', 'default' => 20],
                    required: false,
                    description: 'Maximum number of results'
                ),
            ],
            CQRSQueryMapping: [
                '[_context][shopId]' => '[shopId]',
                '[_context][langId]' => '[languageId]',
                // Allow overriding via query parameters
                '[shopId]' => '[shopId]',
                '[languageId]' => '[languageId]',
                '[phrase]' => '[phrase]',
                '[limit]' => '[limit]',
            ],
        ),
    ],
)]
class ProductCombinationsAssociationSearch
{
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 123])]
    public int $productId;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 456])]
    public int $combinationId;

    #[ApiProperty(openapiContext: ['type' => 'string', 'example' => 'Tee-shirt - Size: M, Color: Red'])]
    public string $name;

    #[ApiProperty(openapiContext: ['type' => 'string', 'example' => 'REF-123'])]
    public string $reference;

    #[ApiProperty(openapiContext: ['type' => 'string', 'example' => 'http://.../img/p/1.jpg'])]
    public string $imageUrl;
}
