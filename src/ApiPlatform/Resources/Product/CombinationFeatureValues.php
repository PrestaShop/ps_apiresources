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
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\FeatureValue\Command\RemoveAllFeatureValuesFromCombinationCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\FeatureValue\Command\SetCombinationFeatureValuesCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\FeatureValue\Query\GetCombinationFeatureValues;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Exception\InvalidProductFeatureValuesFormatException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/combinations/{combinationId}/feature-values',
            requirements: ['combinationId' => '\d+'],
            CQRSQuery: GetCombinationFeatureValues::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[_context][shopId]' => '[shopId]',
            ],
            ApiResourceMapping: [
                '[isCustom]' => '[custom]',
            ],
            experimentalOperation: true,
        ),
        new CQRSCreate(
            uriTemplate: '/products/combinations/{combinationId}/feature-values',
            requirements: ['combinationId' => '\d+'],
            CQRSCommand: SetCombinationFeatureValuesCommand::class,
            scopes: [
                'product_write',
            ],
            status: Response::HTTP_NO_CONTENT,
            output: false,
            experimentalOperation: true,
        ),
        new CQRSDelete(
            uriTemplate: '/products/combinations/{combinationId}/feature-values',
            requirements: ['combinationId' => '\d+'],
            CQRSCommand: RemoveAllFeatureValuesFromCombinationCommand::class,
            scopes: [
                'product_write',
            ],
            status: Response::HTTP_NO_CONTENT,
            output: false,
            experimentalOperation: true,
        ),
    ],
    exceptionToStatus: [
        CombinationNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidProductFeatureValuesFormatException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CombinationFeatureValues
{
    public int $combinationId;

    public int $featureId;

    public int $featureValueId;

    #[LocalizedValue]
    public array $localizedValues;

    public bool $custom;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'description' => 'Feature values to attach to the combination. Each item: {feature_id: int, feature_value_id?: int, custom_values?: {langId: string}}',
        'items' => ['type' => 'object'],
    ])]
    public ?array $featureValues = null;
}
