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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Module\APIResources\ApiPlatform\Metadata\CQRSDeleteCollection;
use PrestaShop\Module\APIResources\ApiPlatform\Metadata\CQRSUpdateCollection;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Product\Dto\ProductFeatureValue;
use PrestaShop\PrestaShop\Core\Domain\Feature\Exception\FeatureNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Feature\Exception\FeatureValueNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Command\RemoveAllFeatureValuesFromProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Command\SetProductFeatureValuesCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Exception\DuplicateFeatureValueAssociationException;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Exception\InvalidAssociatedFeatureException;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Exception\InvalidProductFeatureValuesFormatException;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Query\GetProductFeatureValues;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/{productId}/feature-values',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'List the feature values of a product.',
                description: 'The list is empty for a product without feature values, and for an unknown product. Only the '
                    . 'values of the features associated with the shop of the context are listed.',
            ),
            // The query doesn't check the product: no 404 on this operation
            exceptionToStatus: [
                ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            CQRSQuery: GetProductFeatureValues::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::RESOURCE_MAPPING,
            scopes: ['product_read'],
        ),
        new CQRSUpdateCollection(
            uriTemplate: '/products/{productId}/feature-values',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Replace the feature values of a product.',
                description: 'Requires PrestaShop 9.2.2. Replaces the whole list and answers the feature values of the product. '
                    . 'featureValues is a list of {featureId, featureValueId} objects (the generated request body shows '
                    . 'strings). The values left out are removed whatever the shop, a custom value left out is deleted. An '
                    . 'unknown feature or feature value answers 404, a value of another feature or the same value twice 422.',
            ),
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: SetProductFeatureValuesCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetProductFeatureValues::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::RESOURCE_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
        ),
        new CQRSDeleteCollection(
            uriTemplate: '/products/{productId}/feature-values',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Remove all the feature values of a product.',
                description: 'Requires PrestaShop 9.2.2. Removes the values whatever the shop and deletes the custom ones, '
                    . 'then answers the feature values of the product, an empty list.',
            ),
            // The command only checks the product: none of the errors of the body of the PUT
            exceptionToStatus: [
                ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
                ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
            ],
            CQRSCommand: RemoveAllFeatureValuesFromProductCommand::class,
            CQRSQuery: GetProductFeatureValues::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::RESOURCE_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidProductFeatureValuesFormatException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // The ids of the body: an unknown one answers 404, a value of another feature or a duplicate 422
        FeatureNotFoundException::class => Response::HTTP_NOT_FOUND,
        FeatureValueNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidAssociatedFeatureException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        DuplicateFeatureValueAssociationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
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
     * The text of the value, by locale.
     *
     * @var string[]
     */
    #[LocalizedValue]
    public array $values;

    /**
     * The feature values to set, only read by the update.
     *
     * @var ProductFeatureValue[]|null
     */
    #[ApiProperty(readable: false)]
    #[Assert\NotBlank(groups: ['Update'])]
    #[Assert\Valid]
    public ?array $featureValues = null;

    public const COMMAND_MAPPING = [
        '[featureValues][@index][featureId]' => '[featureValues][@index][feature_id]',
        '[featureValues][@index][featureValueId]' => '[featureValues][@index][feature_value_id]',
    ];

    public const RESOURCE_MAPPING = [
        '[localizedValues]' => '[values]',
    ];

    public const QUERY_MAPPING = [
        '[_context][shopId]' => '[shopId]',
    ];
}
