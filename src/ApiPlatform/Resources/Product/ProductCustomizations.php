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
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Command\RemoveAllCustomizationFieldsFromProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Command\SetProductCustomizationFieldsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Exception\CustomizationFieldConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Exception\CustomizationFieldNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Query\GetProductCustomizationFields;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/customization-fields',
            requirements: ['productId' => '\d+'],
            CQRSQuery: GetProductCustomizationFields::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/customization-fields',
            requirements: ['productId' => '\d+'],
            CQRSCommand: SetProductCustomizationFieldsCommand::class,
            CQRSQuery: GetProductCustomizationFields::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
            CQRSQueryMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}/customization-fields',
            requirements: ['productId' => '\d+'],
            CQRSCommand: RemoveAllCustomizationFieldsFromProductCommand::class,
            scopes: [
                'product_write',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        CustomizationFieldNotFoundException::class => Response::HTTP_NOT_FOUND,
        CustomizationFieldConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductCustomizations
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'nullable' => true],
                'type' => ['type' => 'integer', 'description' => '0 = file, 1 = text'],
                'localized_names' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => 'string'],
                ],
                'is_required' => ['type' => 'boolean'],
                'added_by_module' => ['type' => 'boolean'],
            ],
        ],
        'example' => [
            [
                'id' => null,
                'type' => 1,
                'localized_names' => ['1' => 'Custom text'],
                'is_required' => true,
                'added_by_module' => false,
            ],
        ],
    ])]
    public array $customizationFields;
}
