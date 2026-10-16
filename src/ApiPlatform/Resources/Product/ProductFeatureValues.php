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
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Command\RemoveAllFeatureValuesFromProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Command\SetProductFeatureValuesCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Exception\ProductFeatureValueException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/feature-values',
            requirements: ['productId' => '\d+'],
            output: false,
            CQRSCommand: SetProductFeatureValuesCommand::class,
            scopes: ['product_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}/feature-values',
            requirements: ['productId' => '\d+'],
            output: false,
            CQRSCommand: RemoveAllFeatureValuesFromProductCommand::class,
            scopes: ['product_write'],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductFeatureValueException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductFeatureValues
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    /**
     * Each entry associates a feature with one of its values (or a custom localized value):
     * {"feature_id": 1, "feature_value_id": 2} or {"feature_id": 1, "custom_values": {"en-US": "..."}}.
     *
     * @var array<int, array{feature_id: int, feature_value_id?: int, custom_values?: array<string, string>}>
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => ['type' => 'object'],
        'example' => [['feature_id' => 1, 'feature_value_id' => 2]],
    ])]
    public array $featureValues;
}
