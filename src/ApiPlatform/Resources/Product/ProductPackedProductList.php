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
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Product\Dto\PackedProduct;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\InvalidProductTypeException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Command\RemoveAllProductsFromPackCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Command\SetPackProductsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Exception\ProductPackException;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Query\GetPackedProducts;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/{productId}/pack-products',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'List the products of a pack.',
                description: 'Each item is a product of the pack of the URI: its id, its combination (0 for a '
                    . 'product without combinations), its quantity in the pack, its name in the language of the '
                    . 'context (with the attributes of the combination), its reference ("Ref: ..." in the language '
                    . 'of the context, empty without reference) and the URL of its cover image in the shop of the '
                    . 'context. The list is empty for a product that is not a pack.',
            ),
            CQRSQuery: GetPackedProducts::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['product_read'],
        ),
        new CQRSUpdateCollection(
            uriTemplate: '/products/{productId}/pack-products',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Set the products of a pack.',
                description: 'Requires PrestaShop 9.2.2. Replaces the products of the pack and answers them like the '
                    . 'GET. The product of the URI must be a pack (422 otherwise), and a pack cannot be put in a pack '
                    . '(422).',
            ),
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: SetPackProductsCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetPackedProducts::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
        ),
        new CQRSDeleteCollection(
            uriTemplate: '/products/{productId}/pack-products',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Remove all the products of a pack.',
                description: 'Requires PrestaShop 9.2.2. The product of the URI must be a pack (422 otherwise). '
                    . 'Answers the products of the pack, an empty list.',
            ),
            CQRSCommand: RemoveAllProductsFromPackCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetPackedProducts::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        // The product of the URI is not a pack
        InvalidProductTypeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // A pack put in a pack, or a packed product the core fails to add
        ProductPackException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CombinationConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductPackedProductList
{
    /**
     * The id of the packed product (the pack is the product of the URI in the requests).
     */
    #[ApiProperty(identifier: true)]
    public int $productId;

    public int $combinationId;

    public int $quantity;

    public string $productName;

    public string $reference;

    public string $imageUrl;

    /**
     * The products to put in the pack, only read by the update.
     *
     * @var PackedProduct[]|null
     */
    #[ApiProperty(readable: false)]
    #[Assert\NotBlank(groups: ['Update'])]
    #[Assert\Valid]
    public ?array $products = null;

    public const QUERY_MAPPING = [
        '[productId]' => '[packId]',
        '[_context][langId]' => '[languageId]',
        '[_context][shopConstraint]' => '[shopConstraint]',
    ];

    public const COMMAND_MAPPING = [
        '[productId]' => '[packId]',
        '[products][@index][productId]' => '[products][@index][product_id]',
        '[products][@index][combinationId]' => '[products][@index][combination_id]',
    ];
}
