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
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Command\SetProductImagesForAllShopCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Exception\CannotRemoveCoverException;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Exception\ProductImageConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Query\GetShopProductImages as GetShopProductImagesQuery;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\ShopException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/shop-images',
            requirements: ['productId' => '\d+'],
            CQRSQuery: GetShopProductImagesQuery::class,
            scopes: ['product_read'],
            experimentalOperation: true,
            openapi: new OpenApiOperation(
                summary: 'Get the image/shop associations of a product.',
                description: 'Returns the image ids of the product and its cover image id, grouped by shop. '
                    . self::MULTISHOP_DESCRIPTION
                    . ' An unknown product returns an empty list with a 200, '
                    . 'because the underlying query reads the associations without checking that the product exists.',
                responses: [
                    // The response is the list of associations itself (see ShopProductImagesSerializer), not the resource object
                    '200' => new OpenApiResponse('The image/shop associations of the product.', new \ArrayObject(self::OUTPUT_CONTENT)),
                ],
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/shop-images',
            requirements: ['productId' => '\d+'],
            read: false,
            CQRSCommand: SetProductImagesForAllShopCommand::class,
            CQRSQuery: GetShopProductImagesQuery::class,
            scopes: ['product_write'],
            experimentalOperation: true,
            openapi: new OpenApiOperation(
                summary: 'Set the image/shop associations of a product.',
                description: 'Replaces the image/shop associations of the product and returns them. '
                    . self::MULTISHOP_DESCRIPTION
                    . ' The payload defines the associations of every shop at once: an image missing from a shop is '
                    . 'removed from it, and an image listed under no shop is not associated with any shop anymore so '
                    . 'it is completely deleted. Therefore an image that is the cover of at least one shop cannot be '
                    . 'removed from all the shops, and an image cannot be removed from a shop where it is the cover: '
                    . 'change the cover of that shop first. Both cases are refused with a 422. The cover is not part '
                    . 'of the payload, it is not changed by this operation.',
                responses: [
                    '200' => new OpenApiResponse('The updated image/shop associations of the product.', new \ArrayObject(self::OUTPUT_CONTENT)),
                    '400' => new OpenApiResponse('Invalid input'),
                    '404' => new OpenApiResponse('Product not found'),
                    '422' => new OpenApiResponse('Unprocessable entity'),
                ],
                // The input schema would be generated from the command class, whose constructor only takes the product
                // id (the associations are built by ShopProductImagesSerializer), and the body is a list (see
                // ShopProductImagesSerializer), so it is documented explicitly.
                requestBody: new RequestBody(
                    'The complete image/shop associations of the product.',
                    new \ArrayObject([
                        'application/json' => [
                            'schema' => self::INPUT_SCHEMA,
                            'example' => self::INPUT_EXAMPLE,
                        ],
                    ]),
                    true,
                ),
            ),
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductImageConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // Thrown when the payload removes an image from a shop where it is the cover
        CannotRemoveCoverException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // Thrown when an image/shop association targets an invalid shop id
        ShopException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ShopProductImages
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    /**
     * Image/shop associations of the product, grouped by shop. Read as [{shopId, imageIds, coverImageId}]
     * (see ShopProductImagesSerializer) and written as [{shopId, imageIds}], coverImageId being ignored. Both the request
     * body and the response are this list itself, see ShopProductImagesSerializer.
     */
    #[Assert\NotBlank]
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'shopId' => [new Assert\NotBlank(), new Assert\Type('integer'), new Assert\Positive()],
                'imageIds' => [
                    new Assert\Type('array'),
                    new Assert\All([new Assert\NotBlank(), new Assert\Type('integer'), new Assert\Positive()]),
                ],
                // Accepted so a response can be sent back as is, but ignored and left out of the input schema
                'coverImageId' => new Assert\Optional(),
            ],
        ),
    ])]
    public array $shopImages = [];

    /**
     * The underlying query and command take a product id and no shop constraint, so the shop context
     * parameters are accepted but do not restrict the scope of these operations.
     */
    public const MULTISHOP_DESCRIPTION = 'This endpoint is only useful when multistore is enabled. It covers every '
        . 'shop associated with the product: the shop context parameters (shopId, shopGroupId, shopIds, allShops) '
        . 'do not restrict it.';

    public const INPUT_SCHEMA = [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'required' => ['shopId', 'imageIds'],
            'properties' => [
                'shopId' => ['type' => 'integer'],
                'imageIds' => ['type' => 'array', 'items' => ['type' => 'integer']],
            ],
        ],
    ];

    public const OUTPUT_SCHEMA = [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'shopId' => ['type' => 'integer'],
                'imageIds' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'coverImageId' => [
                    'type' => 'integer',
                    'nullable' => true,
                    'description' => 'Null when the shop has no image.',
                ],
            ],
        ],
    ];

    public const INPUT_EXAMPLE = [
        ['shopId' => 1, 'imageIds' => [1, 2]],
        ['shopId' => 2, 'imageIds' => [3, 4]],
    ];

    public const OUTPUT_EXAMPLE = [
        ['shopId' => 1, 'imageIds' => [1, 2], 'coverImageId' => 1],
        ['shopId' => 2, 'imageIds' => [3, 4], 'coverImageId' => 4],
    ];

    public const OUTPUT_CONTENT = [
        'application/json' => [
            'schema' => self::OUTPUT_SCHEMA,
            'example' => self::OUTPUT_EXAMPLE,
        ],
    ];
}
