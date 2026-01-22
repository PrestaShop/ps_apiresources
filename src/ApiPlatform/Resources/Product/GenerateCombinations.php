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
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\GenerateProductCombinationsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/products/{productId}/generate-combinations',
            CQRSCommand: GenerateProductCombinationsCommand::class,
            scopes: [
                'product_write',
            ],
            ApiResourceMapping: [
                // Used to denormalize the command result
                '[@index][combinationId]' => '[newCombinationIds][@index]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class GenerateCombinations
{
    public int $productId;
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'description' => 'List of new generated combination IDs',
            'items' => [
                'type' => 'integer',
                'description' => 'Combination ID',
            ],
        ]
    )]
    public array $newCombinationIds = [];

    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'description' => 'List of attributes grouped by their attribute group',
                'properties' => [
                    'attributeGroupId' => [
                        'type' => 'number',
                        'description' => 'Attribute group ID',
                    ],
                    'attributeIds' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'integer',
                            'description' => 'Attribute ID',
                        ],
                    ],
                ],
            ],
        ],
    )]
    public array $groupedAttributes;
}
