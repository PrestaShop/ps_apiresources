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
            CQRSQueryMapping: ShopProductImages::QUERY_MAPPING,
            scopes: ['product_read'],
        ),
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/shop-images',
            requirements: ['productId' => '\d+'],
            read: false,
            CQRSCommand: SetProductImagesForAllShopCommand::class,
            CQRSQuery: GetShopProductImagesQuery::class,
            CQRSQueryMapping: ShopProductImages::QUERY_MAPPING,
            scopes: ['product_write'],
            // The input schema is generated from the command class, whose constructor only takes the product id
            // (the associations are added through an adder and built by SetProductImagesForAllShopSerializer), so
            // the request body is documented explicitly with the same shape as the response.
            openapiContext: [
                'requestBody' => [
                    'required' => true,
                    'description' => 'The complete image/shop associations of the product, in the same shape as the response.',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['shopImages'],
                                'properties' => [
                                    'shopImages' => self::SHOP_IMAGES_SCHEMA,
                                ],
                            ],
                            'example' => [
                                'shopImages' => self::SHOP_IMAGES_EXAMPLE,
                            ],
                        ],
                    ],
                ],
            ],
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
     * Image/shop associations of the product, grouped by shop: [{shopId, images: [{imageId, cover}]}].
     * The PUT payload uses the same shape and defines the complete associations: an image listed
     * under no shop is removed from every shop (which fails with a 422 when it is the cover of
     * one of them), and a shop with an empty images list loses all its images. The cover flag is
     * read-only here (it is managed per shop by the product image update operation) and ignored
     * in the payload, so a response can be sent back as is.
     */
    #[ApiProperty(openapiContext: self::SHOP_IMAGES_SCHEMA)]
    #[Assert\NotBlank]
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'shopId' => [new Assert\NotBlank(), new Assert\Positive()],
                'images' => [
                    new Assert\Type('array'),
                    new Assert\All([
                        new Assert\Collection(
                            fields: [
                                'imageId' => [new Assert\NotBlank(), new Assert\Positive()],
                                'cover' => new Assert\Optional([new Assert\Type('bool')]),
                            ],
                        ),
                    ]),
                ],
            ],
        ),
    ])]
    public array $shopImages = [];

    /**
     * The core query returns a collection of per-shop associations, hoisted as a whole
     * into the shopImages property so both operations return the same single resource.
     */
    public const QUERY_MAPPING = [
        '[@index][shopId]' => '[shopImages][@index][shopId]',
        '[@index][productImages]' => '[shopImages][@index][images]',
    ];

    /**
     * OpenAPI schema of the shopImages property, shared by the resource schema and the PUT request body.
     */
    public const SHOP_IMAGES_SCHEMA = [
        'type' => 'array',
        'description' => 'Image/shop associations grouped by shop. In a PUT payload the cover flag is ignored.',
        'items' => [
            'type' => 'object',
            'required' => ['shopId', 'images'],
            'properties' => [
                'shopId' => ['type' => 'integer'],
                'images' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['imageId'],
                        'properties' => [
                            'imageId' => ['type' => 'integer'],
                            'cover' => ['type' => 'boolean', 'description' => 'Read-only, ignored in a PUT payload.'],
                        ],
                    ],
                ],
            ],
        ],
        'example' => self::SHOP_IMAGES_EXAMPLE,
    ];

    public const SHOP_IMAGES_EXAMPLE = [
        [
            'shopId' => 1,
            'images' => [
                ['imageId' => 1, 'cover' => true],
                ['imageId' => 2, 'cover' => false],
            ],
        ],
        [
            'shopId' => 2,
            'images' => [
                ['imageId' => 1, 'cover' => true],
            ],
        ],
    ];
}
