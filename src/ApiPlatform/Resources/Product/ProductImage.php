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
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Command\DeleteProductImageCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Command\UpdateProductImageCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Exception\ProductImageNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Query\GetProductImage;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/images/{imageId}',
            CQRSQuery: GetProductImage::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: ProductImage::QUERY_MAPPING,
        ),
        new CQRSUpdate(
            // We have to force POST request, because we cannot use PUT with files AND data
            method: CQRSUpdate::METHOD_POST,
            uriTemplate: '/products/images/{imageId}',
            inputFormats: ['multipart' => ['multipart/form-data']],
            status: Response::HTTP_OK,
            // Form data value are all string so we disable type enforcement
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            CQRSCommand: UpdateProductImageCommand::class,
            CQRSQuery: GetProductImage::class,
            scopes: [
                'product_write',
            ],
            CQRSQueryMapping: ProductImage::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[image].pathName' => '[filePath]',
                '[legends]' => '[localizedLegends]',
                '[cover]' => '[isCover]',
            ]
        ),
        new CQRSDelete(
            uriTemplate: '/products/images/{imageId}',
            CQRSCommand: DeleteProductImageCommand::class,
        ),
    ],
    exceptionToStatus: [
        ProductImageNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductImage
{
    #[ApiProperty(identifier: true)]
    public int $imageId;

    public string $imageUrl;

    public string $thumbnailUrl;

    #[LocalizedValue]
    public array $legends;

    public bool $cover;

    public int $position;

    public array $shopIds;

    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[localizedLegends]' => '[legends]',
    ];
}
