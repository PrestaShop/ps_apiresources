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
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetCombinationIds;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/{productId}/combinations/ids',
            requirements: ['productId' => '\\d+'],
            CQRSQuery: GetCombinationIds::class,
            scopes: [
                'product_read',
            ],
            parameters: [
                new QueryParameter(
                    key: 'limit',
                    schema: ['type' => 'integer'],
                    required: false,
                    description: 'Maximum number of IDs to return'
                ),
                new QueryParameter(
                    key: 'offset',
                    schema: ['type' => 'integer'],
                    required: false,
                    description: 'Offset of the first ID to return'
                ),
                new QueryParameter(
                    key: 'orderBy',
                    schema: ['type' => 'string'],
                    required: false,
                    description: 'Sort field (id_product_attribute, reference, price, ...)'
                ),
                new QueryParameter(
                    key: 'orderWay',
                    schema: ['type' => 'string'],
                    required: false,
                    description: 'Sort order (ASC or DESC)'
                ),
                // Note: filters can be passed with query params (e.g. filters[name]=foo)
            ],
            CQRSQueryMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
            ApiResourceMapping: [
                // The CombinationId value object is normalized into { value: <int> }
                // We remap this normalized value to the API resource field name
                '[value]' => '[combinationId]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductCombinationIdList
{
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 123])]
    public int $combinationId;
}
