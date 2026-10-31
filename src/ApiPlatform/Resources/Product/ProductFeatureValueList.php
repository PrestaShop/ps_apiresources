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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Command\RemoveAllFeatureValuesFromProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Command\SetProductFeatureValuesCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Exception\InvalidProductFeatureValuesFormatException;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Query\GetProductFeatureValues;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDeleteCollection;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdateCollection;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/{productId}/feature-values',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'List the feature values of a product.',
                description: 'The list is empty for a product without feature values, and for an unknown product.',
            ),
            CQRSQuery: GetProductFeatureValues::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['product_read'],
        ),
        new CQRSUpdateCollection(
            uriTemplate: '/products/{productId}/feature-values',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Replace the feature values of a product.',
                description: 'Requires PrestaShop 9.2.1. Answers the feature values of the product.',
            ),
            CQRSCommand: SetProductFeatureValuesCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetProductFeatureValues::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.2.1',
            ],
        ),
        new CQRSDeleteCollection(
            uriTemplate: '/products/{productId}/feature-values',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Remove all the feature values of a product.',
                description: 'Requires PrestaShop 9.2.1. Answers the feature values of the product, an empty list.',
            ),
            CQRSCommand: RemoveAllFeatureValuesFromProductCommand::class,
            CQRSQuery: GetProductFeatureValues::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.2.1',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidProductFeatureValuesFormatException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductFeatureValueList
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    public int $featureId;

    public int $featureValueId;

    public bool $custom;

    /**
     * The feature values to set, only read by the update.
     */
    #[ApiProperty(readable: false)]
    public array $featureValues;

    public const COMMAND_MAPPING = [
        '[featureValues][@index][featureId]' => '[featureValues][@index][feature_id]',
        '[featureValues][@index][featureValueId]' => '[featureValues][@index][feature_value_id]',
    ];

    public const QUERY_MAPPING = [
        '[_context][shopId]' => '[shopId]',
    ];
}
