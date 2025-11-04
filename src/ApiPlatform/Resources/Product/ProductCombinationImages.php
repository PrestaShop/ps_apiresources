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
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\SetCombinationImagesCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetCombinationForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/products/combinations/{combinationId}/images',
            requirements: ['combinationId' => '\\d+'],
            CQRSCommand: SetCombinationImagesCommand::class,
            // Return updated combination details
            CQRSQuery: GetCombinationForEditing::class,
            openapiContext: [
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['imageIds'],
                                'properties' => [
                                    'imageIds' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                            'example' => [
                                'imageIds' => [24, 25],
                            ],
                        ],
                    ],
                ],
            ],
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[_context][uriVariables][combinationId]' => '[combinationId]',
                '[imageIds]' => '[imageIds]',
            ],
            CQRSQueryMapping: ProductCombination::QUERY_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CombinationNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductCombinationImages
{
    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer', 'example' => 56])]
    #[Assert\Positive]
    public int $combinationId;

    /**
     * List of image IDs to associate with the combination
     *
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [24, 25]])]
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    public array $imageIds;
}
