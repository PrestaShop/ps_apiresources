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
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Product\Dto\CombinationFeatureValueInput;
use PrestaShop\PrestaShop\Core\Domain\Feature\Exception\FeatureNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Feature\Exception\FeatureValueNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\FeatureValue\Command\RemoveAllFeatureValuesFromCombinationCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\FeatureValue\Command\SetCombinationFeatureValuesCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\FeatureValue\Query\GetCombinationFeatureValues;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Exception\DuplicateFeatureValueAssociationException;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Exception\InvalidAssociatedFeatureException;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Exception\InvalidProductFeatureValuesFormatException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The feature values of a combination: the CQRS classes only exist from PrestaShop 9.3.0, every operation
 * declares it.
 */
#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/combinations/{combinationId}/feature-values',
            requirements: ['combinationId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'List the feature values of a combination.',
                description: 'Requires PrestaShop 9.3.0. Only the values of the features associated with the shop '
                    . 'of the context are listed. The list is empty for a combination without feature values, and '
                    . 'for an unknown combination.',
            ),
            // The query doesn't check the combination: no 404 on this operation
            exceptionToStatus: [
                CombinationConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            CQRSQuery: GetCombinationFeatureValues::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::RESOURCE_MAPPING,
            scopes: ['product_read'],
            extraProperties: [
                'minVersion' => '9.3.0',
            ],
        ),
        new CQRSUpdateCollection(
            uriTemplate: '/products/combinations/{combinationId}/feature-values',
            requirements: ['combinationId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Set the feature values of a combination.',
                description: 'Requires PrestaShop 9.3.0. Replaces the feature values of the combination and answers '
                    . 'them like the GET. Each item names its feature and either an existing value (featureValueId) '
                    . 'or the texts of a new custom value (customValues); customValues with a featureValueId update '
                    . 'the texts of that custom value. A custom value left out is deleted. An unknown combination, '
                    . 'feature or feature value answers 404, a value of another feature or the same value twice 422.',
            ),
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: SetCombinationFeatureValuesCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetCombinationFeatureValues::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::RESOURCE_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.3.0',
            ],
        ),
        new CQRSDeleteCollection(
            uriTemplate: '/products/combinations/{combinationId}/feature-values',
            requirements: ['combinationId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Remove all the feature values of a combination.',
                description: 'Requires PrestaShop 9.3.0. Removes the values and deletes the custom ones, then answers '
                    . 'the feature values of the combination, an empty list.',
            ),
            // The command only checks the combination: none of the errors of the body of the PUT
            exceptionToStatus: [
                CombinationConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
                CombinationNotFoundException::class => Response::HTTP_NOT_FOUND,
            ],
            CQRSCommand: RemoveAllFeatureValuesFromCombinationCommand::class,
            CQRSQuery: GetCombinationFeatureValues::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::RESOURCE_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.3.0',
            ],
        ),
    ],
    exceptionToStatus: [
        CombinationConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CombinationNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidProductFeatureValuesFormatException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // The ids of the body: an unknown one answers 404, a value of another feature or a duplicate 422
        FeatureNotFoundException::class => Response::HTTP_NOT_FOUND,
        FeatureValueNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidAssociatedFeatureException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        DuplicateFeatureValueAssociationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CombinationFeatureValueList
{
    #[ApiProperty(identifier: true)]
    public int $combinationId;

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
     * @var CombinationFeatureValueInput[]|null
     */
    #[ApiProperty(readable: false)]
    #[Assert\NotBlank(groups: ['Update'])]
    #[Assert\Valid]
    public ?array $featureValues = null;

    public const QUERY_MAPPING = [
        '[_context][shopId]' => '[shopId]',
    ];

    public const RESOURCE_MAPPING = [
        '[localizedValues]' => '[values]',
    ];

    public const COMMAND_MAPPING = [
        '[featureValues][@index][featureId]' => '[featureValues][@index][feature_id]',
        '[featureValues][@index][featureValueId]' => '[featureValues][@index][feature_value_id]',
        '[featureValues][@index][customValues]' => '[featureValues][@index][custom_values]',
    ];
}
