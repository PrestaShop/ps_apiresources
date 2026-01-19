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
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Command\RemoveSpecificPricePriorityForProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Command\SetSpecificPricePriorityForProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\SpecificPriceException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/products/{productId}/specific-price-priorities',
            output: false,
            CQRSCommand: SetSpecificPricePriorityForProductCommand::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: SpecificPricePriority::COMMAND_MAPPING,
            validationContext: ['groups' => ['update']],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}/specific-price-priorities',
            CQRSCommand: RemoveSpecificPricePriorityForProductCommand::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: SpecificPricePriority::DELETE_COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        SpecificPriceException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class SpecificPricePriority
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    /**
     * @var string[]|null
     */
    #[Assert\NotBlank(groups: ['update'])]
    #[Assert\All([
        'constraints' => [
            new Assert\Choice([
                'choices' => [
                    'id_group',
                    'id_currency',
                    'id_country',
                    'id_shop',
                ],
                'message' => 'Invalid priority value. Valid values are: id_group, id_currency, id_country, id_shop',
            ]),
        ],
        'groups' => ['update'],
    ])]
    #[Assert\Unique(message: 'Priorities cannot duplicate', groups: ['update'])]
    public ?array $priorities = null;

    public const COMMAND_MAPPING = [
        '[productId]' => '[productId]',
        '[priorities]' => '[priorities]',
    ];

    public const DELETE_COMMAND_MAPPING = [
        '[productId]' => '[productId]',
    ];
}
