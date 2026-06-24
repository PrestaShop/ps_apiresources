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
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Attribute\Exception\AttributeConstraintException;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Exception\AttributeGroupConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\GenerateProductCombinationsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CannotGenerateCombinationException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetEditableCombinationsList;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/products/{productId}/combinations',
            requirements: ['productId' => '\\d+'],
            CQRSCommand: GenerateProductCombinationsCommand::class,
            CQRSQuery: GetEditableCombinationsList::class,
            openapiContext: [
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['groupedAttributeIds'],
                                'properties' => [
                                    'groupedAttributeIds' => [
                                        'type' => 'object',
                                        'additionalProperties' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'integer'],
                                        ],
                                    ],
                                ],
                            ],
                            'example' => [
                                'groupedAttributeIds' => [
                                    '1' => [2, 3],
                                    '2' => [10, 14],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[_context][uriVariables][productId]' => '[productId]',
                '[groupedAttributeIds]' => '[groupedAttributeIds]',
            ],
            CQRSQueryMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[_context][langId]' => '[languageId]',
                '[_context][uriVariables][productId]' => '[productId]',
            ],
            ApiResourceMapping: [
                '[combinations]' => '[items]',
                '[totalCombinationsCount]' => '[totalItems]',
            ],
            validationContext: ['groups' => ['Default', 'Create']],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        AttributeConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        AttributeGroupConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotGenerateCombinationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CombinationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class GenerateProductCombinations
{
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 42])]
    #[Assert\Positive(groups: ['Create'])]
    public int $productId;

    #[ApiProperty(openapiContext: ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'integer']], 'example' => ['1' => [2, 3], '2' => [10, 14]]])]
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Type('array', groups: ['Create'])]
    public array $groupedAttributeIds;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'object']])]
    public ?array $items = null;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 2])]
    public ?int $totalItems = null;
}
